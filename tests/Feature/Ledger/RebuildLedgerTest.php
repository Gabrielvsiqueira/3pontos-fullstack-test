<?php

declare(strict_types=1);

use App\Models\CardMonth;
use App\Models\Company;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_p2', 'amount_cents' => 80_000, 'mcc' => '7011']));
    network('POST', '/api/network/events', capturePayload('aut_p2', ['amount_cents' => 30_000, 'sequence' => 1, 'final' => false]));
    network('POST', '/api/network/events', capturePayload('aut_p2', ['amount_cents' => 30_000, 'sequence' => 2, 'final' => false]));
    network('POST', '/api/network/events', capturePayload('aut_p2', ['amount_cents' => 26_000, 'sequence' => 3, 'final' => true]));
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_open', 'card_token' => 'tok_bruno', 'amount_cents' => 20_000]));
});

function projections(): array
{
    return [
        'companies' => Company::query()->orderBy('id')->get(['balance_cents', 'held_cents'])->toArray(),
        'card_months' => CardMonth::query()->orderBy('card_id')->orderBy('month')->get(['card_id', 'month', 'limit_delta_cents'])->toArray(),
        'purchases' => Purchase::query()->orderBy('id')->get(['held_cents', 'captured_cents', 'closed', 'over_capture', 'over_purchase_limit', 'captured_when_declined', 'captured_after_cancellation'])->toArray(),
    ];
}

it('finds no drift after the live writes', function (): void {
    $this->artisan('ledger:rebuild', ['--check' => true])
        ->expectsOutput('Projections match the ledger.')
        ->assertSuccessful();
});

it('rebuilds corrupted projections from the ledger', function (): void {
    $expected = projections();

    DB::table('companies')->update(['balance_cents' => 1, 'held_cents' => 999]);
    DB::table('card_months')->delete();
    DB::table('purchases')->update(['held_cents' => 5, 'captured_cents' => 0, 'over_purchase_limit' => false]);

    $this->artisan('ledger:rebuild')->assertSuccessful();

    expect(projections())->toBe($expected);

    $this->artisan('ledger:rebuild', ['--check' => true])->assertSuccessful();
});

it('reports drift without writing in check mode', function (): void {
    DB::table('companies')->update(['balance_cents' => 1]);

    $this->artisan('ledger:rebuild', ['--check' => true])
        ->expectsOutputToContain('balance 1 → 914000')
        ->assertFailed();

    expect(Company::query()->sole()->balance_cents)->toBe(1);
});

it('keeps answering the same queries after a rebuild', function (): void {
    $before = [
        network('GET', '/api/network/cards/tok_ana/available')->json(),
        network('GET', '/api/network/cards/tok_bruno/available')->json(),
        network('GET', '/api/network/cards/tok_diego/available')->json(),
    ];

    DB::table('card_months')->delete();
    DB::table('companies')->update(['balance_cents' => 0, 'held_cents' => 0]);
    $this->artisan('ledger:rebuild')->assertSuccessful();

    expect([
        network('GET', '/api/network/cards/tok_ana/available')->json(),
        network('GET', '/api/network/cards/tok_bruno/available')->json(),
        network('GET', '/api/network/cards/tok_diego/available')->json(),
    ])->toBe($before);

    assertStatementInvariant('tok_ana');
    assertStatementInvariant('tok_bruno');
});
