<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\Cancellation;
use App\Models\Capture;
use App\Models\Company;
use App\Models\Transaction;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 30_000]))
        ->assertExactJson(['decision' => 'approved']);
});

function reissue(array $original, array $changes = []): array
{
    return ['id' => 'evt_reissued', ...$changes] + $original;
}

it('answers 200 to an identical reissued capture and records nothing new', function (): void {
    $original = capturePayload('aut_1', ['id' => 'evt_original', 'amount_cents' => 10_000, 'final' => false]);

    network('POST', '/api/network/events', $original)->assertAccepted();
    network('POST', '/api/network/events', reissue($original))->assertOk();

    expect(Capture::query()->count())->toBe(1)
        ->and(Transaction::query()->where('type', TransactionType::Capture)->sole()->reference)->toBe('evt_original')
        ->and(Company::query()->sole()->balance_cents)->toBe(990_000);
});

it('answers 409 to a reissued capture with different content', function (array $changes): void {
    $original = capturePayload('aut_1', ['amount_cents' => 10_000, 'final' => false]);

    network('POST', '/api/network/events', $original)->assertAccepted();
    network('POST', '/api/network/events', reissue($original, $changes))->assertConflict();

    expect(Capture::query()->count())->toBe(1)
        ->and(Company::query()->sole()->balance_cents)->toBe(990_000)
        ->and(Company::query()->sole()->held_cents)->toBe(20_000);
})->with([
    'amount' => [['amount_cents' => 15_000]],
    'final' => [['final' => true]],
    'occurred_at' => [['occurred_at' => '2026-09-17T18:00:00Z']],
]);

it('treats a different sequence as a new capture', function (): void {
    $original = capturePayload('aut_1', ['amount_cents' => 10_000, 'final' => false]);

    network('POST', '/api/network/events', $original)->assertAccepted();
    network('POST', '/api/network/events', reissue($original, ['sequence' => 2]))->assertAccepted();

    expect(Capture::query()->count())->toBe(2);
});

it('answers 200 to an identical reissued cancellation', function (): void {
    $original = cancellationPayload('aut_1', ['id' => 'evt_original']);

    network('POST', '/api/network/events', $original)->assertAccepted();
    network('POST', '/api/network/events', reissue($original))->assertOk();

    expect(Cancellation::query()->sole()->network_id)->toBe('evt_original')
        ->and(Transaction::query()->where('type', TransactionType::Cancellation)->sole()->reference)->toBe('evt_original');
});

it('answers 409 to a second cancellation that happened at another time', function (): void {
    $original = cancellationPayload('aut_1');

    network('POST', '/api/network/events', $original)->assertAccepted();
    network('POST', '/api/network/events', reissue($original, ['occurred_at' => '2026-09-17T18:00:00Z']))->assertConflict();

    expect(Cancellation::query()->count())->toBe(1);
});

it('recognizes reissues of events that arrived before their authorization', function (): void {
    $original = capturePayload('aut_later', ['amount_cents' => 10_000, 'final' => true]);

    network('POST', '/api/network/events', $original)->assertAccepted();
    network('POST', '/api/network/events', reissue($original))->assertOk();
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_later', 'amount_cents' => 10_000]));

    expect(Transaction::query()->where('type', TransactionType::Capture)->count())->toBe(1)
        ->and(Company::query()->sole()->balance_cents)->toBe(990_000);
});

it('answers 409 when an event id comes back as another event type', function (): void {
    network('POST', '/api/network/events', capturePayload('aut_1', ['id' => 'evt_same', 'amount_cents' => 1_000]))->assertAccepted();

    network('POST', '/api/network/events', cancellationPayload('aut_1', ['id' => 'evt_same']))->assertConflict();

    expect(Cancellation::query()->exists())->toBeFalse();
});
