<?php

declare(strict_types=1);

it('rejects authorizations that break the contract', function (array $overrides): void {
    network('POST', '/api/network/authorizations', authorizationPayload($overrides))
        ->assertUnprocessable();
})->with([
    'amount as numeric string' => [['amount_cents' => '12990']],
    'amount with fraction' => [['amount_cents' => 129.9]],
    'amount zero' => [['amount_cents' => 0]],
    'amount above maximum' => [['amount_cents' => 100_000_001]],
    'empty id' => [['id' => '']],
    'id longer than 64' => [['id' => str_repeat('a', 65)]],
    'id as number' => [['id' => 123]],
    'card token as number' => [['card_token' => 1]],
    'other currency' => [['currency' => 'USD']],
    'mcc as number' => [['mcc' => 5812]],
    'mcc with 3 digits' => [['mcc' => '581']],
    'merchant missing' => [['merchant' => null]],
    'merchant country lowercase' => [['merchant' => ['name' => 'X', 'city' => 'Y', 'country' => 'br']]],
    'merchant name as number' => [['merchant' => ['name' => 1, 'city' => 'Y', 'country' => 'BR']]],
    'occurred_at with offset' => [['occurred_at' => '2026-09-17T14:03:22-03:00']],
    'occurred_at impossible date' => [['occurred_at' => '2026-02-30T10:00:00Z']],
]);

it('rejects bodies that are not a JSON object', function (string $body): void {
    network('POST', '/api/network/authorizations', $body)->assertUnprocessable();
})->with(['invalid json' => '{"id":', 'empty body' => '', 'list' => '[1,2]']);

it('keeps network strings exactly as sent', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['card_token' => '']))
        ->assertUnprocessable();
});

it('accepts a valid authorization past validation', function (): void {
    $response = network('POST', '/api/network/authorizations', authorizationPayload());

    expect($response->status())->not->toBe(422);
});

it('rejects captures that break the contract', function (array $overrides): void {
    network('POST', '/api/network/events', capturePayload('aut_1', $overrides))
        ->assertUnprocessable();
})->with([
    'unknown type' => [['type' => 'refund']],
    'sequence zero' => [['sequence' => 0]],
    'sequence as string' => [['sequence' => '1']],
    'final as integer' => [['final' => 1]],
    'final as string' => [['final' => 'true']],
    'amount as numeric string' => [['amount_cents' => '100']],
    'missing currency' => [['currency' => null]],
    'missing authorization id' => [['authorization_id' => '']],
]);

it('ignores capture-only fields on a cancellation', function (): void {
    $response = network('POST', '/api/network/events', cancellationPayload('aut_1', [
        'amount_cents' => 'ignored',
        'sequence' => 'ignored',
        'final' => 'ignored',
    ]));

    expect($response->status())->not->toBe(422);
});

it('ignores fields the contract does not describe', function (): void {
    $response = network('POST', '/api/network/authorizations', authorizationPayload(['extra' => ['anything' => true]]));

    expect($response->status())->not->toBe(422);
});
