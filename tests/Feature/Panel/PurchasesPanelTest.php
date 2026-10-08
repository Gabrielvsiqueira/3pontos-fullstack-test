<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Purchases\Pages\ListPurchases;
use App\Models\Purchase;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_ok', 'amount_cents' => 10_000, 'occurred_at' => '2026-09-17T10:00:00Z']));
    network('POST', '/api/network/events', capturePayload('aut_ok', ['id' => 'evt_ok_2', 'amount_cents' => 4_000, 'sequence' => 2, 'final' => true, 'occurred_at' => '2026-09-17T12:00:00Z']));
    network('POST', '/api/network/events', capturePayload('aut_ok', ['id' => 'evt_ok_1', 'amount_cents' => 6_000, 'sequence' => 1, 'final' => false, 'occurred_at' => '2026-09-17T11:00:00Z']));

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_p2', 'amount_cents' => 80_000, 'mcc' => '7011']));
    network('POST', '/api/network/events', capturePayload('aut_p2', ['amount_cents' => 86_000, 'final' => true]));

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_carla', 'card_token' => 'tok_carla']));
    network('POST', '/api/network/events', capturePayload('aut_carla', ['amount_cents' => 1_000]));

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_ghost', 'card_token' => 'tok_ghost']));

    network('POST', '/api/network/events', capturePayload('aut_waiting', ['amount_cents' => 2_000]));

    $this->actingAs(User::query()->where('email', 'marina@acme.test')->sole());
    Filament::setCurrentPanel('admin');
});

function purchases(string ...$ids): Collection
{
    return Purchase::query()->whereIn('network_authorization_id', $ids)->get();
}

it('lists every purchase', function (): void {
    Livewire::test(ListPurchases::class)
        ->assertCanSeeTableRecords(purchases('aut_ok', 'aut_p2', 'aut_carla', 'aut_ghost', 'aut_waiting'))
        ->assertTableColumnStateSet('captures_sum_amount_cents', 10_000, purchases('aut_ok')->sole());
});

it('lists declined authorizations with their reason', function (): void {
    Livewire::test(ListPurchases::class)
        ->set('activeTab', 'declined')
        ->assertCanSeeTableRecords(purchases('aut_carla', 'aut_ghost'))
        ->assertCanNotSeeTableRecords(purchases('aut_ok', 'aut_p2', 'aut_waiting'))
        ->assertSee('card_blocked')
        ->assertSee('card_not_found');
});

it('lists the purchases flagged by decision 5', function (): void {
    Livewire::test(ListPurchases::class)
        ->set('activeTab', 'flagged')
        ->assertCanSeeTableRecords(purchases('aut_p2', 'aut_carla'))
        ->assertCanNotSeeTableRecords(purchases('aut_ok', 'aut_ghost', 'aut_waiting'))
        ->assertSee('over_purchase_limit')
        ->assertSee('captured_when_declined');
});

it('lists events whose authorization has not arrived', function (): void {
    Livewire::test(ListPurchases::class)
        ->set('activeTab', 'waiting')
        ->assertCanSeeTableRecords(purchases('aut_waiting'))
        ->assertCanNotSeeTableRecords(purchases('aut_ok', 'aut_p2', 'aut_carla', 'aut_ghost'))
        ->assertTableColumnStateSet('captures_sum_amount_cents', 2_000, purchases('aut_waiting')->sole());
});

it('shows the history of a purchase in chronological order', function (): void {
    $this->get(route('filament.admin.resources.purchases.view', purchases('aut_ok')->sole()))
        ->assertOk()
        ->assertSeeInOrder(['approved', 'sequence 1', 'evt_ok_1', 'sequence 2 · final', 'evt_ok_2']);
});

it('shows a cancellation in the history', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_cancel', 'amount_cents' => 5_000, 'occurred_at' => '2026-09-17T10:00:00Z']));
    network('POST', '/api/network/events', cancellationPayload('aut_cancel', ['id' => 'evt_cancel', 'occurred_at' => '2026-09-17T11:00:00Z']));

    $this->get(route('filament.admin.resources.purchases.view', purchases('aut_cancel')->sole()))
        ->assertOk()
        ->assertSeeInOrder(['authorization', 'cancellation', 'evt_cancel']);
});

it('forbids cardholders from the purchases pages', function (): void {
    $this->actingAs(User::query()->where('email', 'ana@acme.test')->sole());

    $this->get(route('filament.admin.resources.purchases.index'))->assertForbidden();
    $this->get(route('filament.admin.resources.purchases.view', purchases('aut_ok')->sole()))->assertForbidden();
});
