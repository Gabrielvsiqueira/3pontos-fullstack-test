<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Models\Authorization;
use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    $this->seed();
});

/**
 * @param  list<array<string, mixed>>  $payloads
 * @return list<array{status: int, body: array<string, string>}>
 */
function deliverAtOnce(string $uri, array $payloads): array
{
    $startAt = (string) (microtime(true) + 1.5);
    $env = [
        'APP_ENV' => 'testing',
        'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
    ];

    $results = Process::pool(function (Pool $pool) use ($uri, $payloads, $startAt, $env): void {
        foreach ($payloads as $payload) {
            $pool->path(base_path())->env($env)->timeout(30)
                ->command([PHP_BINARY, 'tests/Concurrency/network-worker.php', $uri, (string) json_encode($payload), $startAt]);
        }
    })->start()->wait();

    return array_values(array_map(
        fn ($result): array => json_decode($result->throw()->output(), true),
        $results->collect()->all(),
    ));
}

it('approves only what fits the limit when different authorizations race', function (): void {
    $payloads = array_map(
        fn (int $i): array => authorizationPayload(['id' => "aut_race_{$i}", 'card_token' => 'tok_bruno', 'amount_cents' => 30_000]),
        range(1, 8),
    );

    $decisions = collect(deliverAtOnce('/api/network/authorizations', $payloads))
        ->map(fn (array $result): string => $result['body']['reason'] ?? $result['body']['decision']);

    expect($decisions->filter(fn (string $d): bool => $d === 'approved'))->toHaveCount(1)
        ->and($decisions->filter(fn (string $d): bool => $d === 'monthly_limit_exceeded'))->toHaveCount(7)
        ->and(Company::query()->sole()->held_cents)->toBe(30_000);
});

it('holds once and answers the same when one authorization is delivered concurrently', function (): void {
    $payload = authorizationPayload(['id' => 'aut_dup', 'card_token' => 'tok_ana', 'amount_cents' => 50_000]);

    $results = deliverAtOnce('/api/network/authorizations', array_fill(0, 6, $payload));

    expect(collect($results)->pluck('status')->unique()->all())->toBe([200])
        ->and(collect($results)->pluck('body')->unique()->values()->all())->toBe([['decision' => 'approved']])
        ->and(Authorization::query()->count())->toBe(1)
        ->and(Transaction::query()->where('type', TransactionType::Authorization)->count())->toBe(1)
        ->and(Company::query()->sole()->held_cents)->toBe(50_000);
});
