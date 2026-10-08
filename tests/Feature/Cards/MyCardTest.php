<?php

declare(strict_types=1);

use App\Cards\Livewire\MyCard;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-10-08 15:00:00');

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_old', 'amount_cents' => 5_000, 'occurred_at' => '2026-09-20T12:00:00Z']));
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_ok', 'amount_cents' => 10_000, 'occurred_at' => '2026-10-08T10:00:00Z']));
    network('POST', '/api/network/events', capturePayload('aut_ok', ['id' => 'evt_ok_2', 'amount_cents' => 4_000, 'sequence' => 2, 'final' => true, 'occurred_at' => '2026-10-08T12:00:00Z']));
    network('POST', '/api/network/events', capturePayload('aut_ok', ['id' => 'evt_ok_1', 'amount_cents' => 6_000, 'sequence' => 1, 'final' => false, 'occurred_at' => '2026-10-08T11:00:00Z']));
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_mcc', 'mcc' => '7995', 'occurred_at' => '2026-10-08T13:00:00Z']));
});

function cardholder(string $name): User
{
    return User::query()->where('email', $name.'@acme.test')->sole();
}

/**
 * @param  LengthAwarePaginator<int, Purchase>  $page
 * @return list<string>
 */
function purchaseIds(LengthAwarePaginator $page): array
{
    return array_map(fn (Purchase $purchase): string => $purchase->network_authorization_id, $page->items());
}

it('shows the available amount and the limit remaining of the current month', function (): void {
    Livewire::actingAs(cardholder('ana'))
        ->test(MyCard::class)
        ->assertViewHas('available', 190_000)
        ->assertViewHas('limitRemaining', 190_000)
        ->assertSee('Limite restante em outubro de 2026')
        ->assertSee('R$ 1.900,00')
        ->assertSee('de R$ 2.000,00 no mês');
});

it('shows the statement of the current month only', function (): void {
    Livewire::actingAs(cardholder('ana'))
        ->test(MyCard::class)
        ->assertViewHas('statement', function (array $statement): bool {
            $references = array_column($statement['transactions'], 'reference');

            return $statement['month'] === '2026-10'
                && in_array('aut_ok', $references, true)
                && in_array('evt_ok_1', $references, true)
                && ! in_array('aut_old', $references, true)
                && ! in_array('aut_mcc', $references, true);
        })
        ->assertSee('Statement de outubro de 2026');
});

it('shows the history of each purchase in order', function (): void {
    Livewire::actingAs(cardholder('ana'))
        ->test(MyCard::class)
        ->assertSeeInOrder([
            'Minhas compras',
            'aut_mcc', 'declined · mcc_blocked',
            'aut_ok', 'approved', 'R$ 100,00 · MCC 5812', 'R$ 60,00 · sequence 1', 'R$ 40,00 · sequence 2 · final',
            'aut_old',
        ]);
});

it('shows a cancellation in the history', function (): void {
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_diego', 'card_token' => 'tok_diego', 'occurred_at' => '2026-10-08T10:00:00Z']));
    network('POST', '/api/network/events', cancellationPayload('aut_diego', ['id' => 'evt_diego_cancel', 'occurred_at' => '2026-10-08T11:00:00Z']));

    Livewire::actingAs(cardholder('diego'))
        ->test(MyCard::class)
        ->assertSeeInOrder(['aut_diego', 'Encerrada', 'authorization', 'cancellation'])
        ->assertViewHas('limitRemaining', 5_000_000);
});

it('shows empty states to a cardholder without purchases', function (): void {
    Livewire::actingAs(cardholder('bruno'))
        ->test(MyCard::class)
        ->assertViewHas('available', 50_000)
        ->assertSee('Nenhuma transaction em outubro de 2026')
        ->assertSee('Nenhuma compra ainda');
});

it('tells a blocked cardholder that nothing is available', function (): void {
    Livewire::actingAs(cardholder('carla'))
        ->test(MyCard::class)
        ->assertViewHas('available', 0)
        ->assertViewHas('limitRemaining', 100_000)
        ->assertSee('Este cartão está bloqueado');
});

it('polls so new messages show up without reloading', function (): void {
    $this->actingAs(cardholder('bruno'))
        ->get('/my-card')
        ->assertSee('wire:poll.'.MyCard::POLL_SECONDS.'s', false);

    expect(MyCard::POLL_SECONDS)->toBeLessThan(5);

    $screen = Livewire::actingAs(cardholder('bruno'))
        ->test(MyCard::class)
        ->assertDontSee('aut_bruno_new');

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_bruno_new', 'card_token' => 'tok_bruno', 'amount_cents' => 2_000]));

    $screen->call('$refresh')
        ->assertSee('aut_bruno_new')
        ->assertViewHas('limitRemaining', 48_000);
});

it('pages through the purchases', function (): void {
    foreach (range(1, MyCard::PER_PAGE + 1) as $number) {
        network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_bruno_'.$number, 'card_token' => 'tok_bruno', 'amount_cents' => 1_000]));
    }

    Livewire::actingAs(cardholder('bruno'))
        ->test(MyCard::class)
        ->assertViewHas('purchases', fn (LengthAwarePaginator $page): bool => purchaseIds($page) === ['aut_bruno_6', 'aut_bruno_5', 'aut_bruno_4', 'aut_bruno_3', 'aut_bruno_2'])
        ->call('nextPage')
        ->assertViewHas('purchases', fn (LengthAwarePaginator $page): bool => purchaseIds($page) === ['aut_bruno_1']);
});
