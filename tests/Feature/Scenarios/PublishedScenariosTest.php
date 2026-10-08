<?php

declare(strict_types=1);

use Passa\Ledger\Models\Purchase;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');
});

function availableOf(string $token): array
{
    return network('GET', "/api/network/cards/{$token}/available")->assertOk()->json();
}

function authorizeScenario(string $id, string $token, int $amount, string $mcc): array
{
    return network('POST', '/api/network/authorizations', authorizationPayload([
        'id' => $id, 'card_token' => $token, 'amount_cents' => $amount, 'mcc' => $mcc,
    ]))->assertOk()->json();
}

function captureScenario(string $id, int $amount, int $sequence, bool $final): void
{
    network('POST', '/api/network/events', capturePayload($id, [
        'amount_cents' => $amount, 'sequence' => $sequence, 'final' => $final,
    ]))->assertAccepted();
}

it('matches the published result of P1', function (): void {
    foreach ([12_990, 4_500, 30_000, 8_010, 1_500] as $i => $amount) {
        expect(authorizeScenario("aut_p1_{$i}", 'tok_ana', $amount, '5812'))->toBe(['decision' => 'approved']);
        captureScenario("aut_p1_{$i}", $amount, 1, true);
    }

    expect(availableOf('tok_ana'))->toBe(['available_cents' => 143_000, 'limit_remaining_cents' => 143_000])
        ->and(availableOf('tok_diego'))->toBe(['available_cents' => 943_000, 'limit_remaining_cents' => 5_000_000])
        ->and(assertStatementInvariant('tok_ana')['limit_remaining_cents'])->toBe(143_000)
        ->and(Purchase::query()->get()->contains->isFlagged())->toBeFalse();
});

it('matches the MODEL.md prediction for P2', function (): void {
    $expected = [
        'authorization 800' => [120_000, 120_000, 920_000],
        'capture 300 (1)' => [120_000, 120_000, 920_000],
        'capture 300 (2)' => [120_000, 120_000, 920_000],
        'capture 260 (3, final)' => [114_000, 114_000, 914_000],
    ];

    $steps = [
        fn () => expect(authorizeScenario('aut_p2', 'tok_ana', 80_000, '7011'))->toBe(['decision' => 'approved']),
        fn () => captureScenario('aut_p2', 30_000, 1, false),
        fn () => captureScenario('aut_p2', 30_000, 2, false),
        fn () => captureScenario('aut_p2', 26_000, 3, true),
    ];

    foreach (array_keys($expected) as $i => $label) {
        $steps[$i]();
        [$anaAvailable, $anaRemaining, $diegoAvailable] = $expected[$label];

        expect(availableOf('tok_ana'))->toBe(['available_cents' => $anaAvailable, 'limit_remaining_cents' => $anaRemaining], $label)
            ->and(availableOf('tok_diego')['available_cents'])->toBe($diegoAvailable, $label);

        assertStatementInvariant('tok_ana');
    }

    $purchase = Purchase::query()->where('network_authorization_id', 'aut_p2')->sole();

    expect($purchase->over_purchase_limit)->toBeTrue()
        ->and($purchase->over_capture)->toBeFalse();
});

it('matches the MODEL.md prediction for P3', function (): void {
    expect(authorizeScenario('aut_p3', 'tok_bruno', 40_000, '5812'))->toBe(['decision' => 'approved'])
        ->and(availableOf('tok_bruno'))->toBe(['available_cents' => 10_000, 'limit_remaining_cents' => 10_000])
        ->and(availableOf('tok_diego')['available_cents'])->toBe(960_000);

    captureScenario('aut_p3', 48_000, 1, true);
    expect(availableOf('tok_bruno'))->toBe(['available_cents' => 2_000, 'limit_remaining_cents' => 2_000])
        ->and(availableOf('tok_diego')['available_cents'])->toBe(952_000);

    expect(authorizeScenario('aut_p3_50', 'tok_bruno', 5_000, '5812'))->toBe(['decision' => 'declined', 'reason' => 'monthly_limit_exceeded'])
        ->and(availableOf('tok_bruno'))->toBe(['available_cents' => 2_000, 'limit_remaining_cents' => 2_000])
        ->and(availableOf('tok_diego')['available_cents'])->toBe(952_000);

    expect(authorizeScenario('aut_p3_20', 'tok_bruno', 2_000, '5812'))->toBe(['decision' => 'approved'])
        ->and(availableOf('tok_bruno'))->toBe(['available_cents' => 0, 'limit_remaining_cents' => 0])
        ->and(availableOf('tok_diego')['available_cents'])->toBe(950_000);

    assertStatementInvariant('tok_bruno');

    expect(Purchase::query()->get()->contains->isFlagged())->toBeFalse();
});
