<?php

declare(strict_types=1);

dataset('network endpoints', [
    'authorizations' => ['POST', '/api/network/authorizations'],
    'events' => ['POST', '/api/network/events'],
    'available' => ['GET', '/api/network/cards/tok_ana/available'],
    'statement' => ['GET', '/api/network/cards/tok_ana/statement'],
]);

it('rejects requests without a signature', function (string $method, string $uri): void {
    $this->json($method, $uri, authorizationPayload())->assertUnauthorized();
})->with('network endpoints');

it('rejects a signature made with another secret', function (string $method, string $uri): void {
    $timestamp = (string) now()->getTimestamp();

    network($method, $uri, headers: [
        'X-Network-Timestamp' => $timestamp,
        'X-Network-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.', 'wrong-secret'),
    ])->assertUnauthorized();
})->with('network endpoints');

it('rejects a body changed after signing', function (): void {
    $timestamp = (string) now()->getTimestamp();
    $signed = (string) json_encode(authorizationPayload(['amount_cents' => 100]));
    $sent = str_replace('"amount_cents":100', '"amount_cents":100000', $signed);

    network('POST', '/api/network/authorizations', $sent, headers: [
        'X-Network-Timestamp' => $timestamp,
        'X-Network-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$signed, (string) config('network.secret')),
    ])->assertUnauthorized();
});

it('rejects timestamps more than five minutes away', function (int $offset): void {
    network('POST', '/api/network/authorizations', authorizationPayload(), now()->getTimestamp() + $offset)
        ->assertUnauthorized();
})->with(['301s in the past' => -301, '301s in the future' => 301]);

it('lets a correctly signed request through', function (string $method, string $uri): void {
    $response = network($method, $uri, $method === 'POST' ? authorizationPayload() : null);

    expect($response->status())->not->toBe(401);
})->with('network endpoints');

it('lets timestamps exactly five minutes away through', function (int $offset): void {
    $this->freezeTime();

    $response = network('POST', '/api/network/authorizations', authorizationPayload(), now()->getTimestamp() + $offset);

    expect($response->status())->not->toBe(401);
})->with(['300s in the past' => -300, '300s in the future' => 300]);

it('checks the signature before validating the body', function (): void {
    $this->postJson('/api/network/authorizations', ['amount_cents' => 'not a number'])
        ->assertUnauthorized();
});
