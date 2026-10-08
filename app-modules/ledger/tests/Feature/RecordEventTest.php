<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Passa\Ledger\Enums\TransactionType;
use Passa\Ledger\Ledger;
use Passa\Ledger\Models\Capture;
use Passa\Ledger\Models\Card;
use Passa\Ledger\Models\Company;
use Passa\Ledger\Models\Purchase;
use Passa\Ledger\Models\Transaction;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');
});

function sendEvent(array $payload): TestResponse
{
    return network('POST', '/api/network/events', $payload);
}

function approve(string $id, string $token, int $amount, string $mcc = '5812'): void
{
    network('POST', '/api/network/authorizations', authorizationPayload([
        'id' => $id, 'card_token' => $token, 'amount_cents' => $amount, 'mcc' => $mcc,
    ]))->assertExactJson(['decision' => 'approved']);
}

function remaining(string $token): int
{
    return resolve(Ledger::class)->limitRemaining(Card::query()->where('token', $token)->sole(), '2026-09');
}

function companyState(): array
{
    $company = Company::query()->sole();

    return ['balance' => $company->balance_cents, 'held' => $company->held_cents, 'available' => $company->availableCents()];
}

it('consumes the hold with a capture inside it', function (): void {
    approve('aut_1', 'tok_ana', 30_000);

    sendEvent(capturePayload('aut_1', ['id' => 'evt_1', 'amount_cents' => 30_000, 'final' => true]))->assertAccepted();

    $transaction = Transaction::query()->where('type', TransactionType::Capture)->sole();

    expect($transaction->limit_delta_cents)->toBe(0)
        ->and($transaction->balance_delta_cents)->toBe(-30_000)
        ->and($transaction->held_delta_cents)->toBe(-30_000)
        ->and($transaction->reference)->toBe('evt_1')
        ->and(remaining('tok_ana'))->toBe(170_000)
        ->and(companyState())->toBe(['balance' => 970_000, 'held' => 0, 'available' => 970_000]);
});

it('follows P2 message by message', function (): void {
    approve('aut_p2', 'tok_ana', 80_000, '7011');

    $steps = [[], [30_000, 1, false], [30_000, 2, false], [26_000, 3, true]];
    $expected = [
        [120_000, 920_000, 1_000_000, 80_000],
        [120_000, 920_000, 970_000, 50_000],
        [120_000, 920_000, 940_000, 20_000],
        [114_000, 914_000, 914_000, 0],
    ];

    foreach ($steps as $i => $step) {
        if ($step !== []) {
            [$amount, $sequence, $final] = $step;
            sendEvent(capturePayload('aut_p2', ['amount_cents' => $amount, 'sequence' => $sequence, 'final' => $final]))->assertAccepted();
        }

        $company = companyState();

        expect([remaining('tok_ana'), min(remaining('tok_diego'), $company['available']), $company['balance'], $company['held']])
            ->toBe($expected[$i]);
    }
});

it('follows P3 message by message', function (): void {
    approve('aut_p3', 'tok_bruno', 40_000);
    expect(remaining('tok_bruno'))->toBe(10_000)
        ->and(companyState()['available'])->toBe(960_000);

    sendEvent(capturePayload('aut_p3', ['amount_cents' => 48_000, 'final' => true]))->assertAccepted();
    expect(remaining('tok_bruno'))->toBe(2_000)
        ->and(companyState()['available'])->toBe(952_000);

    network('POST', '/api/network/authorizations', authorizationPayload(['card_token' => 'tok_bruno', 'amount_cents' => 5_000]))
        ->assertExactJson(['decision' => 'declined', 'reason' => 'monthly_limit_exceeded']);
    expect(remaining('tok_bruno'))->toBe(2_000)
        ->and(companyState()['available'])->toBe(952_000);

    approve('aut_p3_c', 'tok_bruno', 2_000);
    expect(remaining('tok_bruno'))->toBe(0)
        ->and(companyState()['available'])->toBe(950_000);
});

it('releases what is left of the hold on the final capture', function (): void {
    approve('aut_1', 'tok_ana', 30_000);

    sendEvent(capturePayload('aut_1', ['amount_cents' => 25_000, 'final' => true]));

    expect(Transaction::query()->where('type', TransactionType::Capture)->sole()->limit_delta_cents)->toBe(5_000)
        ->and(remaining('tok_ana'))->toBe(175_000)
        ->and(companyState())->toBe(['balance' => 975_000, 'held' => 0, 'available' => 975_000]);
});

it('debits only the excess of a capture above the hold from the limit', function (): void {
    approve('aut_1', 'tok_ana', 30_000);

    sendEvent(capturePayload('aut_1', ['amount_cents' => 34_000, 'final' => true]));

    expect(Transaction::query()->where('type', TransactionType::Capture)->sole()->limit_delta_cents)->toBe(-4_000)
        ->and(remaining('tok_ana'))->toBe(166_000);
});

it('releases the hold on a cancellation', function (): void {
    approve('aut_1', 'tok_ana', 30_000);

    sendEvent(capturePayload('aut_1', ['amount_cents' => 10_000, 'final' => false]));
    sendEvent(cancellationPayload('aut_1', ['id' => 'evt_cancel']))->assertAccepted();

    $cancellation = Transaction::query()->where('type', TransactionType::Cancellation)->sole();

    expect($cancellation->limit_delta_cents)->toBe(20_000)
        ->and($cancellation->held_delta_cents)->toBe(-20_000)
        ->and($cancellation->reference)->toBe('evt_cancel')
        ->and(remaining('tok_ana'))->toBe(190_000)
        ->and(companyState())->toBe(['balance' => 990_000, 'held' => 0, 'available' => 990_000]);
});

it('writes no transaction for a cancellation that releases nothing', function (): void {
    approve('aut_1', 'tok_ana', 30_000);
    sendEvent(capturePayload('aut_1', ['amount_cents' => 30_000, 'final' => true]));

    sendEvent(cancellationPayload('aut_1'))->assertAccepted();

    expect(Transaction::query()->where('type', TransactionType::Cancellation)->exists())->toBeFalse();
});

it('debits a capture after a cancellation in full', function (): void {
    approve('aut_1', 'tok_ana', 30_000);
    sendEvent(cancellationPayload('aut_1'));

    sendEvent(capturePayload('aut_1', ['amount_cents' => 10_000]))->assertAccepted();

    expect(remaining('tok_ana'))->toBe(190_000)
        ->and(companyState())->toBe(['balance' => 990_000, 'held' => 0, 'available' => 990_000]);
});

it('reaches the same totals when the final capture arrives before a partial one', function (): void {
    approve('aut_1', 'tok_ana', 80_000, '7011');

    sendEvent(capturePayload('aut_1', ['amount_cents' => 26_000, 'sequence' => 3, 'final' => true]));
    sendEvent(capturePayload('aut_1', ['amount_cents' => 30_000, 'sequence' => 1, 'final' => false]));
    sendEvent(capturePayload('aut_1', ['amount_cents' => 30_000, 'sequence' => 2, 'final' => false]));

    expect(remaining('tok_ana'))->toBe(114_000)
        ->and(companyState())->toBe(['balance' => 914_000, 'held' => 0, 'available' => 914_000]);
});

it('stores an event that arrives before its authorization without moving money', function (): void {
    sendEvent(capturePayload('aut_later', ['amount_cents' => 10_000]))->assertAccepted();

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_later')->sole();

    expect($purchase->card_id)->toBeNull()
        ->and(Capture::query()->where('purchase_id', $purchase->id)->count())->toBe(1)
        ->and(Transaction::query()->where('type', TransactionType::Capture)->exists())->toBeFalse()
        ->and(companyState()['balance'])->toBe(1_000_000);
});

it('answers 200 to a repeated delivery and records nothing new', function (string $type): void {
    approve('aut_1', 'tok_ana', 30_000);
    $payload = $type === 'capture'
        ? capturePayload('aut_1', ['id' => 'evt_twice', 'amount_cents' => 10_000, 'final' => false])
        : cancellationPayload('aut_1', ['id' => 'evt_twice']);

    sendEvent($payload)->assertAccepted();
    sendEvent($payload)->assertOk();

    expect(Transaction::query()->where('reference', 'evt_twice')->count())->toBe(1)
        ->and(remaining('tok_ana'))->toBe($type === 'capture' ? 170_000 : 200_000);
})->with(['capture', 'cancellation']);
