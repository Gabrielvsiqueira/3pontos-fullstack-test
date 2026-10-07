<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Ledger\Actions\RecordDeposit;
use App\Ledger\Ledger;
use App\Models\Card;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Transaction;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');
});

function pendingRemaining(string $token): int
{
    return resolve(Ledger::class)->limitRemaining(Card::query()->where('token', $token)->sole(), '2026-09');
}

function purchaseTotals(string $networkAuthorizationId): array
{
    $purchase = Purchase::query()->where('network_authorization_id', $networkAuthorizationId)->sole();
    $transactions = Transaction::query()->where('purchase_id', $purchase->id);

    return [
        'limit' => (int) $transactions->clone()->sum('limit_delta_cents'),
        'balance' => (int) $transactions->clone()->sum('balance_delta_cents'),
        'held' => (int) $transactions->clone()->sum('held_delta_cents'),
        'purchase_held' => $purchase->held_cents,
        'captured' => $purchase->captured_cents,
        'closed' => $purchase->closed,
    ];
}

it('applies a final capture that arrived before its authorization', function (): void {
    network('POST', '/api/network/events', capturePayload('aut_1', ['amount_cents' => 10_000, 'final' => true]))->assertAccepted();

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 10_000]))
        ->assertExactJson(['decision' => 'approved']);

    expect(pendingRemaining('tok_ana'))->toBe(190_000)
        ->and(Company::query()->sole()->balance_cents)->toBe(990_000)
        ->and(Company::query()->sole()->held_cents)->toBe(0)
        ->and(Transaction::query()->where('type', TransactionType::Authorization)->exists())->toBeFalse()
        ->and(Transaction::query()->where('type', TransactionType::Capture)->sole()->limit_delta_cents)->toBe(-10_000);
});

it('keeps holding the rest when only a partial capture arrived first', function (): void {
    network('POST', '/api/network/events', capturePayload('aut_1', ['amount_cents' => 10_000, 'final' => false]));

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 30_000]));

    expect(purchaseTotals('aut_1'))->toMatchArray(['limit' => -30_000, 'balance' => -10_000, 'held' => 20_000, 'purchase_held' => 20_000])
        ->and(pendingRemaining('tok_ana'))->toBe(170_000);
});

it('decides without counting the events that arrived first', function (): void {
    network('POST', '/api/network/events', capturePayload('aut_1', ['amount_cents' => 50_000, 'final' => true]));

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'card_token' => 'tok_bruno', 'amount_cents' => 50_000]))
        ->assertExactJson(['decision' => 'approved']);

    expect(pendingRemaining('tok_bruno'))->toBe(0);
});

it('holds nothing when a cancellation arrived first', function (): void {
    network('POST', '/api/network/events', cancellationPayload('aut_1'));

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 30_000]))
        ->assertExactJson(['decision' => 'approved']);

    expect(purchaseTotals('aut_1'))->toMatchArray(['limit' => 0, 'balance' => 0, 'held' => 0, 'closed' => true])
        ->and(pendingRemaining('tok_ana'))->toBe(200_000);
});

it('debits captures of a declined authorization in full', function (): void {
    network('POST', '/api/network/events', capturePayload('aut_1', ['amount_cents' => 10_000, 'final' => true]));

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'card_token' => 'tok_carla', 'amount_cents' => 10_000]))
        ->assertExactJson(['decision' => 'declined', 'reason' => 'card_blocked']);

    expect(purchaseTotals('aut_1'))->toMatchArray(['limit' => -10_000, 'balance' => -10_000, 'held' => 0])
        ->and(pendingRemaining('tok_carla'))->toBe(90_000);
});

it('keeps events of an unknown card out of the balance', function (): void {
    network('POST', '/api/network/events', capturePayload('aut_1', ['amount_cents' => 10_000]));

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'card_token' => 'tok_ghost']))
        ->assertExactJson(['decision' => 'declined', 'reason' => 'card_not_found']);

    expect(Transaction::query()->where('type', TransactionType::Capture)->exists())->toBeFalse()
        ->and(Company::query()->sole()->balance_cents)->toBe(1_000_000);
});

it('reaches the same totals in every arrival order', function (): void {
    resolve(RecordDeposit::class)->handle(Company::query()->sole(), 2_000_000);

    $messages = [
        'auth' => fn (string $id): array => ['/api/network/authorizations', authorizationPayload(['id' => $id, 'card_token' => 'tok_diego', 'amount_cents' => 80_000, 'mcc' => '7011'])],
        'c1' => fn (string $id): array => ['/api/network/events', capturePayload($id, ['amount_cents' => 30_000, 'sequence' => 1, 'final' => false, 'occurred_at' => '2026-09-17T16:00:00Z'])],
        'c2' => fn (string $id): array => ['/api/network/events', capturePayload($id, ['amount_cents' => 30_000, 'sequence' => 2, 'final' => false, 'occurred_at' => '2026-09-17T16:10:00Z'])],
        'c3' => fn (string $id): array => ['/api/network/events', capturePayload($id, ['amount_cents' => 26_000, 'sequence' => 3, 'final' => true, 'occurred_at' => '2026-09-17T16:20:00Z'])],
    ];

    $permutations = [[]];
    foreach (array_keys($messages) as $key) {
        $next = [];
        foreach ($permutations as $permutation) {
            $counter = count($permutation);
            for ($i = 0; $i <= $counter; $i++) {
                $next[] = [...array_slice($permutation, 0, $i), $key, ...array_slice($permutation, $i)];
            }
        }

        $permutations = $next;
    }

    expect($permutations)->toHaveCount(24);

    foreach ($permutations as $n => $order) {
        $id = "aut_order_{$n}";

        foreach ($order as $key) {
            [$uri, $payload] = $messages[$key]($id);
            expect(network('POST', $uri, $payload)->status())->toBeIn([200, 202]);
        }

        expect(purchaseTotals($id))->toBe([
            'limit' => -86_000,
            'balance' => -86_000,
            'held' => 0,
            'purchase_held' => 0,
            'captured' => 86_000,
            'closed' => true,
        ], 'order: '.implode(' → ', $order));
    }
});
