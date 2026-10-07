<?php

declare(strict_types=1);

use App\Models\Purchase;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');
});

function flagsOf(string $networkAuthorizationId): array
{
    return Purchase::query()
        ->where('network_authorization_id', $networkAuthorizationId)
        ->sole()
        ->only(['over_capture', 'over_purchase_limit', 'captured_when_declined', 'captured_after_cancellation']);
}

function flagged(string ...$flags): array
{
    return [
        'over_capture' => in_array('over_capture', $flags, true),
        'over_purchase_limit' => in_array('over_purchase_limit', $flags, true),
        'captured_when_declined' => in_array('captured_when_declined', $flags, true),
        'captured_after_cancellation' => in_array('captured_after_cancellation', $flags, true),
    ];
}

function authorizeFor(string $id, array $overrides = []): void
{
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => $id, ...$overrides]));
}

function captureFor(string $id, array $overrides = []): void
{
    network('POST', '/api/network/events', capturePayload($id, $overrides));
}

it('flags P2 above the purchase limit but within the margin', function (): void {
    authorizeFor('aut_p2', ['amount_cents' => 80_000, 'mcc' => '7011']);
    captureFor('aut_p2', ['amount_cents' => 30_000, 'sequence' => 1, 'final' => false]);
    captureFor('aut_p2', ['amount_cents' => 30_000, 'sequence' => 2, 'final' => false]);

    expect(flagsOf('aut_p2'))->toBe(flagged());

    captureFor('aut_p2', ['amount_cents' => 26_000, 'sequence' => 3, 'final' => true]);

    expect(flagsOf('aut_p2'))->toBe(flagged('over_purchase_limit'));
});

it('does not flag P3 captured exactly at the margin', function (): void {
    authorizeFor('aut_p3', ['card_token' => 'tok_bruno', 'amount_cents' => 40_000, 'mcc' => '5812']);
    captureFor('aut_p3', ['amount_cents' => 48_000, 'final' => true]);

    expect(flagsOf('aut_p3'))->toBe(flagged());
});

it('flags captures above the margin', function (string $mcc, int $captured, bool $flag): void {
    authorizeFor('aut_1', ['card_token' => 'tok_diego', 'amount_cents' => 10_000, 'mcc' => $mcc]);
    captureFor('aut_1', ['amount_cents' => $captured]);

    expect(flagsOf('aut_1')['over_capture'])->toBe($flag);
})->with([
    'restaurant at 120%' => ['5812', 12_000, false],
    'restaurant above 120%' => ['5812', 12_001, true],
    'hotel above 120%' => ['7011', 12_001, true],
    'car rental above 120%' => ['7512', 12_001, true],
    'other mcc at authorized' => ['5411', 10_000, false],
    'other mcc above authorized' => ['5411', 10_001, true],
]);

it('flags captures of a declined authorization', function (): void {
    authorizeFor('aut_1', ['card_token' => 'tok_carla']);
    captureFor('aut_1', ['amount_cents' => 5_000]);

    expect(flagsOf('aut_1'))->toBe(flagged('captured_when_declined'));
});

it('does not flag a declined authorization without captures', function (): void {
    authorizeFor('aut_1', ['card_token' => 'tok_carla']);

    expect(flagsOf('aut_1'))->toBe(flagged());
});

it('flags a capture that happened after the cancellation, in any arrival order', function (bool $cancellationFirst): void {
    authorizeFor('aut_1', ['amount_cents' => 10_000]);

    $cancel = fn () => network('POST', '/api/network/events', cancellationPayload('aut_1', ['occurred_at' => '2026-09-17T16:00:00Z']));
    $capture = fn () => captureFor('aut_1', ['amount_cents' => 5_000, 'occurred_at' => '2026-09-17T16:00:01Z']);

    $cancellationFirst ? [$cancel(), $capture()] : [$capture(), $cancel()];

    expect(flagsOf('aut_1'))->toBe(flagged('captured_after_cancellation'));
})->with(['cancellation first' => true, 'capture first' => false]);

it('does not flag a capture that happened before the cancellation even if it arrives later', function (): void {
    authorizeFor('aut_1', ['amount_cents' => 10_000]);

    network('POST', '/api/network/events', cancellationPayload('aut_1', ['occurred_at' => '2026-09-17T16:00:00Z']));
    captureFor('aut_1', ['amount_cents' => 5_000, 'occurred_at' => '2026-09-17T15:59:59Z']);

    expect(flagsOf('aut_1'))->toBe(flagged());
});

it('evaluates the flags when events arrived before the authorization', function (): void {
    captureFor('aut_1', ['amount_cents' => 90_000, 'final' => true]);

    expect(flagsOf('aut_1'))->toBe(flagged());

    authorizeFor('aut_1', ['amount_cents' => 70_000, 'mcc' => '5411']);

    expect(flagsOf('aut_1'))->toBe(flagged('over_capture', 'over_purchase_limit'));
});
