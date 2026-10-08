<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');
});

function statement(string $token, string $query = ''): TestResponse
{
    return network('GET', "/api/network/cards/{$token}/statement{$query}");
}

it('answers an empty month with the full limit', function (): void {
    statement('tok_ana', '?month=2026-09')
        ->assertOk()
        ->assertExactJson([
            'month' => '2026-09',
            'limit_cents' => 200_000,
            'limit_remaining_cents' => 200_000,
            'transactions' => [],
        ]);
});

it('uses the current month when none is given', function (): void {
    statement('tok_ana')->assertJsonPath('month', '2026-09');

    $this->travelTo('2026-10-01 03:00:00');

    statement('tok_ana')->assertJsonPath('month', '2026-10');
});

it('lists P2 with the hold and the running limit', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_p2', 'amount_cents' => 80_000, 'mcc' => '7011', 'occurred_at' => '2026-09-17T14:03:22Z']));
    network('POST', '/api/network/events', capturePayload('aut_p2', ['id' => 'evt_1', 'amount_cents' => 30_000, 'sequence' => 1, 'final' => false, 'occurred_at' => '2026-09-17T16:00:00Z']));
    network('POST', '/api/network/events', capturePayload('aut_p2', ['id' => 'evt_2', 'amount_cents' => 30_000, 'sequence' => 2, 'final' => false, 'occurred_at' => '2026-09-17T17:00:00Z']));
    network('POST', '/api/network/events', capturePayload('aut_p2', ['id' => 'evt_3', 'amount_cents' => 26_000, 'sequence' => 3, 'final' => true, 'occurred_at' => '2026-09-17T18:00:00Z']));

    $statement = assertStatementInvariant('tok_ana', '2026-09');

    expect($statement['limit_remaining_cents'])->toBe(114_000)
        ->and($statement['transactions'])->toBe([
            ['occurred_at' => '2026-09-17T14:03:22Z', 'type' => 'authorization', 'amount_cents' => -80_000, 'reference' => 'aut_p2', 'limit_remaining_after_cents' => 120_000],
            ['occurred_at' => '2026-09-17T16:00:00Z', 'type' => 'capture', 'amount_cents' => 0, 'reference' => 'evt_1', 'limit_remaining_after_cents' => 120_000],
            ['occurred_at' => '2026-09-17T17:00:00Z', 'type' => 'capture', 'amount_cents' => 0, 'reference' => 'evt_2', 'limit_remaining_after_cents' => 120_000],
            ['occurred_at' => '2026-09-17T18:00:00Z', 'type' => 'capture', 'amount_cents' => -6_000, 'reference' => 'evt_3', 'limit_remaining_after_cents' => 114_000],
        ]);
});

it('shows the release of a cancellation and leaves out declined authorizations', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 30_000]));
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_declined', 'mcc' => '7995']));
    network('POST', '/api/network/events', cancellationPayload('aut_1', ['id' => 'evt_cancel']));

    $statement = assertStatementInvariant('tok_ana');

    expect(collect($statement['transactions'])->pluck('amount_cents', 'reference')->all())
        ->toBe(['aut_1' => -30_000, 'evt_cancel' => 30_000]);
});

it('adds late transactions to past months and keeps the invariant', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_aug', 'card_token' => 'tok_bruno', 'amount_cents' => 10_000, 'occurred_at' => '2026-08-31T12:00:00Z']));
    network('POST', '/api/network/events', capturePayload('aut_aug', ['amount_cents' => 12_000, 'final' => true]));

    $august = assertStatementInvariant('tok_bruno', '2026-08');

    expect($august['limit_remaining_cents'])->toBe(38_000)
        ->and($august['transactions'])->toHaveCount(2);

    assertStatementInvariant('tok_bruno', '2026-09');
});

it('keeps the invariant when events arrive before the authorization', function (): void {
    network('POST', '/api/network/events', capturePayload('aut_1', ['amount_cents' => 20_000, 'sequence' => 2, 'final' => true]));
    network('POST', '/api/network/events', capturePayload('aut_1', ['amount_cents' => 5_000, 'sequence' => 1, 'final' => false]));
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 30_000]));

    expect(assertStatementInvariant('tok_ana')['limit_remaining_cents'])->toBe(175_000);
});

it('rejects an invalid month', function (string $month): void {
    statement('tok_ana', '?month='.$month)->assertUnprocessable();
})->with(['2026-13', '2026-00', '2026-9', '26-09', '2026-09-01', 'september', '']);

it('rejects a month sent as a list', function (): void {
    statement('tok_ana', '?month[]=2026-09')->assertUnprocessable();
});

it('answers 404 for an unknown card', function (): void {
    statement('tok_nobody', '?month=2026-09')->assertNotFound();
});
