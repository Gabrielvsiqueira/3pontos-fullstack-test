<?php

declare(strict_types=1);

use App\Network\NetworkSignature;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature e Unit compartilham o TestCase da aplicação e o RefreshDatabase.
| Os testes rodam no Postgres de .env.testing, o mesmo servidor que você usa
| em desenvolvimento, em um banco separado.
|
*/

pest()->group('feature')->in('Feature');
pest()->group('unit')->in('Unit');
pest()->group('concurrency')->in('Concurrency');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

pest()->printer()->compact();

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * @param  array<mixed>|string|null  $body
 * @param  array<string, string>  $headers
 */
function network(string $method, string $uri, array|string|null $body = null, ?int $timestamp = null, array $headers = []): TestResponse
{
    $content = is_array($body) ? (string) json_encode($body) : (string) $body;
    $timestamp = (string) ($timestamp ?? now()->getTimestamp());

    $headers += [
        'X-Network-Timestamp' => $timestamp,
        'X-Network-Signature' => NetworkSignature::fromConfig()->sign($timestamp, $content),
    ];

    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.mb_strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call($method, $uri, server: $server, content: $content);
}

/**
 * @param  list<array{0: string, 1: array<string, mixed>}>  $requests
 * @return list<array{status: int, body: array<string, string>}>
 */
function deliverAtOnce(array $requests): array
{
    $startAt = (string) (microtime(true) + 1.5);
    $env = [
        'APP_ENV' => 'testing',
        'DB_DATABASE' => (string) config('database.connections.pgsql.database'),
    ];

    $results = Process::pool(function (Pool $pool) use ($requests, $startAt, $env): void {
        foreach ($requests as [$uri, $payload]) {
            $pool->path(base_path())->env($env)->timeout(30)
                ->command([PHP_BINARY, 'tests/Concurrency/network-worker.php', $uri, (string) json_encode($payload), $startAt]);
        }
    })->start()->wait();

    return array_values(array_map(
        fn ($result): array => json_decode($result->throw()->output(), true),
        $results->collect()->all(),
    ));
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function authorizationPayload(array $overrides = []): array
{
    return array_replace([
        'id' => 'aut_'.Str::ulid(),
        'card_token' => 'tok_ana',
        'amount_cents' => 12990,
        'currency' => 'BRL',
        'mcc' => '5812',
        'merchant' => ['name' => 'Restaurante Bom Prato', 'city' => 'Porto Alegre', 'country' => 'BR'],
        'occurred_at' => now()->utc()->format('Y-m-d\\TH:i:s\\Z'),
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function capturePayload(string $authorizationId, array $overrides = []): array
{
    return array_replace([
        'id' => 'evt_'.Str::ulid(),
        'type' => 'capture',
        'occurred_at' => now()->utc()->format('Y-m-d\\TH:i:s\\Z'),
        'authorization_id' => $authorizationId,
        'amount_cents' => 12990,
        'currency' => 'BRL',
        'sequence' => 1,
        'final' => true,
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cancellationPayload(string $authorizationId, array $overrides = []): array
{
    return array_replace([
        'id' => 'evt_'.Str::ulid(),
        'type' => 'cancellation',
        'occurred_at' => now()->utc()->format('Y-m-d\\TH:i:s\\Z'),
        'authorization_id' => $authorizationId,
    ], $overrides);
}
