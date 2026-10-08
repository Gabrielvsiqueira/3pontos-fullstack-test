<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Passa\Admin\Filament\Resources\Cards\Pages\ListCards;
use Passa\Admin\Filament\Resources\Cards\Pages\ViewCard;
use Passa\Admin\Filament\Resources\Cards\RelationManagers\StatementRelationManager;
use Passa\Ledger\Models\Card;
use Passa\Ledger\Models\Transaction;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 30_000]));
    network('POST', '/api/network/events', capturePayload('aut_1', ['id' => 'evt_1', 'amount_cents' => 25_000, 'final' => true]));
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_aug', 'amount_cents' => 10_000, 'occurred_at' => '2026-08-10T12:00:00Z']));

    $this->actingAs(User::query()->where('email', 'marina@acme.test')->sole());
    Filament::setCurrentPanel('admin');
});

function card(string $token): Card
{
    return Card::query()->where('token', $token)->sole();
}

it('lists every card with its current limit remaining and available', function (): void {
    Livewire::test(ListCards::class)
        ->assertCanSeeTableRecords(Card::query()->get())
        ->assertTableColumnStateSet('limit_remaining', 175_000, card('tok_ana'))
        ->assertTableColumnStateSet('available', 175_000, card('tok_ana'))
        ->assertTableColumnStateSet('limit_remaining', 100_000, card('tok_carla'))
        ->assertTableColumnStateSet('available', 0, card('tok_carla'))
        ->assertTableColumnStateSet('limit_remaining', 5_000_000, card('tok_diego'))
        ->assertTableColumnStateSet('available', 965_000, card('tok_diego'));
});

it('shows a card with its rules', function (): void {
    $this->get(route('filament.admin.resources.cards.view', card('tok_ana')))
        ->assertOk()
        ->assertSee('tok_ana')
        ->assertSee('7995');
});

it('shows the statement of the current month with the running limit', function (): void {
    $transactions = Transaction::query()->where('card_id', card('tok_ana')->id)->where('month', '2026-09')->get();

    Livewire::test(StatementRelationManager::class, ['ownerRecord' => card('tok_ana'), 'pageClass' => ViewCard::class])
        ->assertCanSeeTableRecords($transactions)
        ->assertTableColumnStateSet('limit_remaining_after_cents', 170_000, $transactions->firstWhere('reference', 'aut_1'))
        ->assertTableColumnStateSet('limit_remaining_after_cents', 175_000, $transactions->firstWhere('reference', 'evt_1'))
        ->assertSee('limite restante R$ 1.750,00');
});

it('shows the statement of any month', function (): void {
    $august = Transaction::query()->where('reference', 'aut_aug')->get();

    Livewire::test(StatementRelationManager::class, ['ownerRecord' => card('tok_ana'), 'pageClass' => ViewCard::class])
        ->filterTable('month', '2026-08')
        ->assertCanSeeTableRecords($august)
        ->assertCanNotSeeTableRecords(Transaction::query()->where('month', '2026-09')->get())
        ->assertTableColumnStateSet('limit_remaining_after_cents', 190_000, $august->sole());
});

it('offers no way to create, edit or delete cards', function (): void {
    expect(Route::has('filament.admin.resources.cards.create'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.cards.edit'))->toBeFalse();

    Livewire::test(ListCards::class)
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});

it('forbids cardholders from the cards pages', function (): void {
    $this->actingAs(User::query()->where('email', 'ana@acme.test')->sole());

    $this->get(route('filament.admin.resources.cards.index'))->assertForbidden();
    $this->get(route('filament.admin.resources.cards.view', card('tok_ana')))->assertForbidden();
});
