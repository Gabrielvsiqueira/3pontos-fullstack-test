<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');
});

function replayMessages(): array
{
    $at = fn (string $time): string => "2026-09-17T{$time}Z";
    $auth = fn (string $id, string $token, int $amount, string $mcc, string $time): array => ['/api/network/authorizations', authorizationPayload(['id' => $id, 'card_token' => $token, 'amount_cents' => $amount, 'mcc' => $mcc, 'occurred_at' => $at($time)])];
    $capture = fn (string $id, string $authorization, int $amount, int $sequence, bool $final, string $time): array => ['/api/network/events', capturePayload($authorization, ['id' => $id, 'amount_cents' => $amount, 'sequence' => $sequence, 'final' => $final, 'occurred_at' => $at($time)])];
    $cancel = fn (string $id, string $authorization, string $time): array => ['/api/network/events', cancellationPayload($authorization, ['id' => $id, 'occurred_at' => $at($time)])];

    $messages = [];

    foreach ([12_990, 4_500, 30_000, 8_010, 1_500] as $i => $amount) {
        $messages[] = $auth("aut_p1_{$i}", 'tok_ana', $amount, '5812', '10:0'.$i.':00');
        $messages[] = $capture("evt_p1_{$i}", "aut_p1_{$i}", $amount, 1, true, '11:0'.$i.':00');
    }

    $messages[] = $auth('aut_p2', 'tok_ana', 80_000, '7011', '12:00:00');
    $messages[] = $capture('evt_p2_1', 'aut_p2', 30_000, 1, false, '12:10:00');
    $messages[] = $capture('evt_p2_2', 'aut_p2', 30_000, 2, false, '12:20:00');
    $messages[] = $capture('evt_p2_3', 'aut_p2', 26_000, 3, true, '12:30:00');

    $messages[] = $auth('aut_p3', 'tok_bruno', 40_000, '5812', '13:00:00');
    $messages[] = $capture('evt_p3', 'aut_p3', 48_000, 1, true, '13:10:00');

    $messages[] = $auth('aut_hotel', 'tok_diego', 300_000, '7011', '14:00:00');
    $messages[] = $capture('evt_hotel_1', 'aut_hotel', 100_000, 1, false, '14:10:00');
    $messages[] = $cancel('evt_hotel_cancel', 'aut_hotel', '14:20:00');
    $messages[] = $capture('evt_hotel_late', 'aut_hotel', 50_000, 2, true, '14:30:00');

    $messages[] = $auth('aut_carla', 'tok_carla', 10_000, '5411', '15:00:00');
    $messages[] = $capture('evt_carla', 'aut_carla', 10_000, 1, true, '15:10:00');

    $messages[] = $auth('aut_dropped', 'tok_diego', 20_000, '5411', '16:00:00');
    $messages[] = $cancel('evt_dropped', 'aut_dropped', '16:10:00');

    $messages[] = $messages[1];
    $messages[] = ['/api/network/events', ['id' => 'evt_p2_3_reissued'] + $messages[13][1]];
    $messages[] = $messages[10];

    return $messages;
}

function replaySnapshot(): array
{
    $cards = collect(['tok_ana', 'tok_bruno', 'tok_carla', 'tok_diego'])
        ->mapWithKeys(fn (string $token): array => [$token => [
            ...network('GET', "/api/network/cards/{$token}/available")->json(),
            'statement_remaining' => assertStatementInvariant($token)['limit_remaining_cents'],
        ]]);

    $company = Company::query()->sole();

    return [
        'cards' => $cards->all(),
        'company' => ['balance' => $company->balance_cents, 'held' => $company->held_cents],
        'purchases' => Purchase::query()->orderBy('network_authorization_id')->get()
            ->mapWithKeys(fn (Purchase $purchase): array => [$purchase->network_authorization_id => $purchase->only([
                'held_cents', 'captured_cents', 'closed', 'over_capture', 'over_purchase_limit', 'captured_when_declined', 'captured_after_cancellation',
            ])])->all(),
    ];
}

function replay(array $messages): array
{
    DB::beginTransaction();

    foreach ($messages as [$uri, $payload]) {
        expect(network('POST', $uri, $payload)->status())->toBeIn([200, 202]);
    }

    $snapshot = replaySnapshot();

    DB::rollBack();

    return $snapshot;
}

it('reaches the same state in any arrival order', function (): void {
    $messages = replayMessages();
    $expected = replay($messages);

    expect($expected['cards']['tok_ana']['limit_remaining_cents'])->toBe(57_000)
        ->and($expected['company'])->toBe(['balance' => 1_000_000 - 57_000 - 86_000 - 48_000 - 150_000 - 10_000, 'held' => 0])
        ->and($expected['purchases']['aut_p2']['over_purchase_limit'])->toBeTrue()
        ->and($expected['purchases']['aut_hotel']['captured_after_cancellation'])->toBeTrue()
        ->and($expected['purchases']['aut_carla']['captured_when_declined'])->toBeTrue();

    expect(replay(array_reverse($messages)))->toBe($expected, 'reversed');

    foreach (range(1, 10) as $seed) {
        mt_srand($seed);
        $shuffled = $messages;
        shuffle($shuffled);

        expect(replay($shuffled))->toBe($expected, "shuffle seed {$seed}");
    }
});

it('rebuilds to the same projections after any order', function (): void {
    $messages = replayMessages();
    mt_srand(99);
    shuffle($messages);

    foreach ($messages as [$uri, $payload]) {
        network('POST', $uri, $payload);
    }

    $this->artisan('ledger:rebuild', ['--check' => true])->assertSuccessful();
});
