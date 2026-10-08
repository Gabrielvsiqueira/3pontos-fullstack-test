<?php

declare(strict_types=1);

use Passa\Ledger\Enums\TransactionType;
use Passa\Ledger\Models\Capture;
use Passa\Ledger\Models\Company;
use Passa\Ledger\Models\Purchase;
use Passa\Ledger\Models\Transaction;

beforeEach(function (): void {
    $this->seed();
});

function concurrentPurchaseTotals(string $networkAuthorizationId): array
{
    $purchase = Purchase::query()->where('network_authorization_id', $networkAuthorizationId)->sole();
    $transactions = Transaction::query()->where('purchase_id', $purchase->id);

    return [
        'limit' => (int) $transactions->clone()->sum('limit_delta_cents'),
        'balance' => (int) $transactions->clone()->sum('balance_delta_cents'),
        'held' => $purchase->held_cents,
    ];
}

it('records a capture once when it is delivered four times at once', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 30_000]));
    $capture = capturePayload('aut_1', ['amount_cents' => 30_000, 'final' => true]);

    $statuses = collect(deliverAtOnce(array_fill(0, 4, ['/api/network/events', $capture])))->pluck('status')->sort()->values();

    expect($statuses->all())->toBe([200, 200, 200, 202])
        ->and(Capture::query()->count())->toBe(1)
        ->and(Transaction::query()->where('type', TransactionType::Capture)->count())->toBe(1)
        ->and(Company::query()->sole()->balance_cents)->toBe(970_000);
});

it('records a capture once when it is reissued at the same time as the original', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 30_000]));
    $original = capturePayload('aut_1', ['amount_cents' => 10_000, 'final' => false]);

    $requests = array_map(
        fn (int $i): array => ['/api/network/events', ['id' => "evt_reissue_{$i}"] + $original],
        range(1, 4),
    );

    $statuses = collect(deliverAtOnce($requests))->pluck('status')->sort()->values();

    expect($statuses->all())->toBe([200, 200, 200, 202])
        ->and(Capture::query()->count())->toBe(1)
        ->and(Company::query()->sole()->balance_cents)->toBe(990_000);
});

it('reaches the P2 totals when the authorization and its captures arrive together', function (): void {
    $requests = [
        ['/api/network/authorizations', authorizationPayload(['id' => 'aut_p2', 'amount_cents' => 80_000, 'mcc' => '7011'])],
        ['/api/network/events', capturePayload('aut_p2', ['amount_cents' => 30_000, 'sequence' => 1, 'final' => false, 'occurred_at' => '2026-09-17T16:00:00Z'])],
        ['/api/network/events', capturePayload('aut_p2', ['amount_cents' => 30_000, 'sequence' => 2, 'final' => false, 'occurred_at' => '2026-09-17T16:10:00Z'])],
        ['/api/network/events', capturePayload('aut_p2', ['amount_cents' => 26_000, 'sequence' => 3, 'final' => true, 'occurred_at' => '2026-09-17T16:20:00Z'])],
    ];

    $results = deliverAtOnce($requests);

    expect(collect($results)->pluck('status')->every(fn (int $status): bool => in_array($status, [200, 202], true)))->toBeTrue()
        ->and($results[0]['body'])->toBe(['decision' => 'approved'])
        ->and(concurrentPurchaseTotals('aut_p2'))->toBe(['limit' => -86_000, 'balance' => -86_000, 'held' => 0])
        ->and(Purchase::query()->where('network_authorization_id', 'aut_p2')->sole()->over_purchase_limit)->toBeTrue();
});

it('keeps the company balance consistent when events and authorizations of different cards race', function (): void {
    $cards = ['tok_ana', 'tok_bruno', 'tok_diego'];
    $requests = [];

    foreach ($cards as $card) {
        foreach (range(1, 3) as $i) {
            network('POST', '/api/network/authorizations', authorizationPayload(['id' => "aut_{$card}_{$i}", 'card_token' => $card, 'amount_cents' => 10_000]))
                ->assertExactJson(['decision' => 'approved']);
            $requests[] = ['/api/network/events', capturePayload("aut_{$card}_{$i}", ['amount_cents' => 8_000, 'final' => true])];
        }

        $requests[] = ['/api/network/authorizations', authorizationPayload(['id' => "aut_{$card}_new", 'card_token' => $card, 'amount_cents' => 5_000])];
    }

    $results = deliverAtOnce($requests);
    $company = Company::query()->sole();

    expect(collect($results)->pluck('status')->every(fn (int $status): bool => in_array($status, [200, 202], true)))->toBeTrue()
        ->and($company->balance_cents)->toBe(1_000_000 - 9 * 8_000)
        ->and($company->held_cents)->toBe(3 * 5_000)
        ->and($company->balance_cents)->toBe((int) Transaction::query()->sum('balance_delta_cents'))
        ->and($company->held_cents)->toBe((int) Transaction::query()->sum('held_delta_cents'));
});
