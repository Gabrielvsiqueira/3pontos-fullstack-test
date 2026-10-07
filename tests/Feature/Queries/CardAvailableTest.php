<?php

declare(strict_types=1);

use App\Models\Card;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');
});

function available(string $token): TestResponse
{
    return network('GET', "/api/network/cards/{$token}/available");
}

it('answers the seeded cards', function (string $token, int $available, int $limitRemaining): void {
    available($token)
        ->assertOk()
        ->assertExactJson(['available_cents' => $available, 'limit_remaining_cents' => $limitRemaining]);
})->with([
    'ana' => ['tok_ana', 200_000, 200_000],
    'bruno' => ['tok_bruno', 50_000, 50_000],
    'carla is blocked' => ['tok_carla', 0, 100_000],
    'diego is capped by the company' => ['tok_diego', 1_000_000, 5_000_000],
]);

it('counts holds and captures of the current month', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 30_000]));

    available('tok_ana')->assertExactJson(['available_cents' => 170_000, 'limit_remaining_cents' => 170_000]);
    available('tok_diego')->assertExactJson(['available_cents' => 970_000, 'limit_remaining_cents' => 5_000_000]);

    network('POST', '/api/network/events', capturePayload('aut_1', ['amount_cents' => 25_000, 'final' => true]));

    available('tok_ana')->assertExactJson(['available_cents' => 175_000, 'limit_remaining_cents' => 175_000]);
    available('tok_diego')->assertExactJson(['available_cents' => 975_000, 'limit_remaining_cents' => 5_000_000]);
});

it('ignores purchases attributed to another month', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['card_token' => 'tok_bruno', 'amount_cents' => 30_000, 'occurred_at' => '2026-08-20T12:00:00Z']));

    available('tok_bruno')->assertExactJson(['available_cents' => 50_000, 'limit_remaining_cents' => 50_000]);
});

it('uses the current month in America/Sao_Paulo', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['card_token' => 'tok_bruno', 'amount_cents' => 30_000, 'occurred_at' => '2026-09-30T23:00:00Z']));

    $this->travelTo('2026-10-01 02:59:59');
    available('tok_bruno')->assertJsonPath('limit_remaining_cents', 20_000);

    $this->travelTo('2026-10-01 03:00:00');
    available('tok_bruno')->assertJsonPath('limit_remaining_cents', 50_000);
});

it('shows a negative limit and no availability after an over capture', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'card_token' => 'tok_bruno', 'amount_cents' => 50_000]));
    network('POST', '/api/network/events', capturePayload('aut_1', ['amount_cents' => 60_000, 'final' => true]));

    available('tok_bruno')->assertExactJson(['available_cents' => 0, 'limit_remaining_cents' => -10_000]);
});

it('answers zero available for a card blocked after spending', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['card_token' => 'tok_ana', 'amount_cents' => 30_000]));
    Card::query()->where('token', 'tok_ana')->update(['blocked' => true]);

    available('tok_ana')->assertExactJson(['available_cents' => 0, 'limit_remaining_cents' => 170_000]);
});

it('answers 404 for an unknown card', function (): void {
    available('tok_nobody')->assertNotFound();
});
