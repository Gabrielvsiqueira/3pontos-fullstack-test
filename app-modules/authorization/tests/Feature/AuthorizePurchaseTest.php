<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Passa\Ledger\Actions\RecordDeposit;
use Passa\Ledger\Enums\Decision;
use Passa\Ledger\Enums\TransactionType;
use Passa\Ledger\Ledger;
use Passa\Ledger\Models\Authorization;
use Passa\Ledger\Models\Card;
use Passa\Ledger\Models\Company;
use Passa\Ledger\Models\Purchase;
use Passa\Ledger\Models\Transaction;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');
});

function authorize(array $overrides = []): TestResponse
{
    return network('POST', '/api/network/authorizations', authorizationPayload($overrides));
}

function limitRemaining(string $token, string $month = '2026-09'): int
{
    return resolve(Ledger::class)->limitRemaining(Card::query()->where('token', $token)->sole(), $month);
}

it('approves and holds the authorized amount', function (): void {
    authorize(['id' => 'aut_1', 'amount_cents' => 12990])
        ->assertOk()
        ->assertExactJson(['decision' => 'approved']);

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_1')->sole();
    $transaction = Transaction::query()->where('purchase_id', $purchase->id)->sole();

    expect($purchase->held_cents)->toBe(12990)
        ->and($purchase->month)->toBe('2026-09')
        ->and($transaction->type)->toBe(TransactionType::Authorization)
        ->and($transaction->reference)->toBe('aut_1')
        ->and($transaction->limit_delta_cents)->toBe(-12990)
        ->and($transaction->balance_delta_cents)->toBe(0)
        ->and($transaction->held_delta_cents)->toBe(12990)
        ->and(limitRemaining('tok_ana'))->toBe(200_000 - 12990)
        ->and(Company::query()->sole()->availableCents())->toBe(1_000_000 - 12990);
});

it('declines with the first failing reason', function (array $overrides, string $reason): void {
    authorize($overrides)
        ->assertOk()
        ->assertExactJson(['decision' => 'declined', 'reason' => $reason]);
})->with([
    'unknown card' => [['card_token' => 'tok_nobody'], 'card_not_found'],
    'blocked card' => [['card_token' => 'tok_carla'], 'card_blocked'],
    'blocked mcc' => [['card_token' => 'tok_ana', 'mcc' => '7995'], 'mcc_blocked'],
    'above purchase limit' => [['card_token' => 'tok_ana', 'amount_cents' => 80_001], 'amount_over_purchase_limit'],
    'above monthly limit' => [['card_token' => 'tok_bruno', 'amount_cents' => 50_001], 'monthly_limit_exceeded'],
    'above company funds' => [['card_token' => 'tok_diego', 'amount_cents' => 1_000_001], 'insufficient_funds'],
]);

it('checks the rules in the documented order', function (): void {
    Card::query()->where('token', 'tok_carla')->update(['blocked_mccs' => ['7995'], 'purchase_limit_cents' => 100]);

    authorize(['card_token' => 'tok_carla', 'mcc' => '7995', 'amount_cents' => 2_000_000])
        ->assertExactJson(['decision' => 'declined', 'reason' => 'card_blocked']);

    authorize(['card_token' => 'tok_ana', 'mcc' => '7995', 'amount_cents' => 90_000])
        ->assertExactJson(['decision' => 'declined', 'reason' => 'mcc_blocked']);

    authorize(['card_token' => 'tok_ana', 'amount_cents' => 90_000])
        ->assertExactJson(['decision' => 'declined', 'reason' => 'amount_over_purchase_limit']);

    authorize(['card_token' => 'tok_diego', 'amount_cents' => 2_000_000])
        ->assertExactJson(['decision' => 'declined', 'reason' => 'insufficient_funds']);
});

it('approves amounts exactly at each boundary', function (string $token, int $amount): void {
    authorize(['card_token' => $token, 'amount_cents' => $amount])
        ->assertExactJson(['decision' => 'approved']);
})->with([
    'purchase limit' => ['tok_ana', 80_000],
    'monthly limit' => ['tok_bruno', 50_000],
    'company funds' => ['tok_diego', 1_000_000],
]);

it('declines above what is left of the monthly limit', function (): void {
    authorize(['card_token' => 'tok_bruno', 'amount_cents' => 40_000])->assertExactJson(['decision' => 'approved']);
    authorize(['card_token' => 'tok_bruno', 'amount_cents' => 10_001])->assertExactJson(['decision' => 'declined', 'reason' => 'monthly_limit_exceeded']);
    authorize(['card_token' => 'tok_bruno', 'amount_cents' => 10_000])->assertExactJson(['decision' => 'approved']);

    expect(limitRemaining('tok_bruno'))->toBe(0);
});

it('shares the company balance across cards', function (): void {
    authorize(['card_token' => 'tok_ana', 'amount_cents' => 80_000])->assertExactJson(['decision' => 'approved']);

    authorize(['card_token' => 'tok_diego', 'amount_cents' => 920_001])
        ->assertExactJson(['decision' => 'declined', 'reason' => 'insufficient_funds']);
    authorize(['card_token' => 'tok_diego', 'amount_cents' => 920_000])
        ->assertExactJson(['decision' => 'approved']);
});

it('stores a declined authorization without moving money', function (): void {
    authorize(['id' => 'aut_carla', 'card_token' => 'tok_carla']);

    $authorization = Authorization::query()->sole();

    expect($authorization->decision)->toBe(Decision::Declined)
        ->and($authorization->transaction()->exists())->toBeFalse()
        ->and(Transaction::query()->where('type', TransactionType::Authorization)->count())->toBe(0);
});

it('stores unknown card tokens as sent', function (): void {
    authorize(['id' => 'aut_ghost', 'card_token' => 'tok_ghost']);

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_ghost')->sole();

    expect($purchase->card_id)->toBeNull()
        ->and(Authorization::query()->sole()->card_token)->toBe('tok_ghost');
});

it('returns the stored response to a repeated delivery without holding twice', function (): void {
    $payload = authorizationPayload(['id' => 'aut_twice', 'amount_cents' => 30_000]);

    network('POST', '/api/network/authorizations', $payload)->assertExactJson(['decision' => 'approved']);
    network('POST', '/api/network/authorizations', $payload)->assertExactJson(['decision' => 'approved']);

    expect(Authorization::query()->count())->toBe(1)
        ->and(Transaction::query()->where('type', TransactionType::Authorization)->count())->toBe(1)
        ->and(limitRemaining('tok_ana'))->toBe(170_000);
});

it('does not decide again when a repeated delivery arrives after the state changed', function (): void {
    $payload = authorizationPayload(['id' => 'aut_poor', 'card_token' => 'tok_diego', 'amount_cents' => 1_500_000]);

    network('POST', '/api/network/authorizations', $payload)
        ->assertExactJson(['decision' => 'declined', 'reason' => 'insufficient_funds']);

    resolve(RecordDeposit::class)->handle(Company::query()->sole(), 1_000_000);

    network('POST', '/api/network/authorizations', $payload)
        ->assertExactJson(['decision' => 'declined', 'reason' => 'insufficient_funds']);
});

it('rolls everything back and answers 5xx when the write fails before the commit', function (): void {
    $failing = true;
    Transaction::created(function () use (&$failing): void {
        throw_if($failing, RuntimeException::class, 'The database went away.');
    });

    $payload = authorizationPayload(['id' => 'aut_crash', 'card_token' => 'tok_ana', 'amount_cents' => 30_000]);

    network('POST', '/api/network/authorizations', $payload)->assertServerError();

    expect(Purchase::query()->where('network_authorization_id', 'aut_crash')->exists())->toBeFalse()
        ->and(Authorization::query()->count())->toBe(0)
        ->and(Transaction::query()->where('type', TransactionType::Authorization)->count())->toBe(0)
        ->and(Company::query()->sole()->held_cents)->toBe(0)
        ->and(limitRemaining('tok_ana'))->toBe(200_000);

    $failing = false;

    network('POST', '/api/network/authorizations', $payload)->assertExactJson(['decision' => 'approved']);

    expect(Authorization::query()->count())->toBe(1)
        ->and(Transaction::query()->where('type', TransactionType::Authorization)->count())->toBe(1)
        ->and(Company::query()->sole()->held_cents)->toBe(30_000)
        ->and(limitRemaining('tok_ana'))->toBe(170_000);
});

it('attributes the purchase to its month in America/Sao_Paulo', function (): void {
    authorize(['id' => 'aut_late', 'card_token' => 'tok_bruno', 'amount_cents' => 50_000, 'occurred_at' => '2026-10-01T02:59:59Z'])
        ->assertExactJson(['decision' => 'approved']);

    expect(Purchase::query()->where('network_authorization_id', 'aut_late')->value('month'))->toBe('2026-09')
        ->and(limitRemaining('tok_bruno', '2026-09'))->toBe(0)
        ->and(limitRemaining('tok_bruno', '2026-10'))->toBe(50_000);

    authorize(['card_token' => 'tok_bruno', 'amount_cents' => 50_000, 'occurred_at' => '2026-10-01T03:00:00Z'])
        ->assertExactJson(['decision' => 'approved']);
});

it('checks offline authorizations against the month they happened in', function (): void {
    authorize(['card_token' => 'tok_bruno', 'amount_cents' => 50_000, 'occurred_at' => '2026-08-20T12:00:00Z'])
        ->assertExactJson(['decision' => 'approved']);

    authorize(['card_token' => 'tok_bruno', 'amount_cents' => 50_000])
        ->assertExactJson(['decision' => 'approved']);

    authorize(['card_token' => 'tok_bruno', 'amount_cents' => 1, 'occurred_at' => '2026-08-25T12:00:00Z'])
        ->assertExactJson(['decision' => 'declined', 'reason' => 'monthly_limit_exceeded']);
});
