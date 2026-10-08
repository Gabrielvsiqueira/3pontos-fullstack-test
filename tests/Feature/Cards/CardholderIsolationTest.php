<?php

declare(strict_types=1);

use App\Cards\Livewire\MyCard;
use App\Models\Card;
use App\Models\Purchase;
use App\Models\User;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-10-08 15:00:00');

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_ana_1', 'amount_cents' => 10_000]));

    network('POST', '/api/network/authorizations', authorizationPayload([
        'id' => 'aut_bruno_secret',
        'card_token' => 'tok_bruno',
        'amount_cents' => 4_321,
        'mcc' => '5942',
        'merchant' => ['name' => 'Livraria do Bruno', 'city' => 'Recife', 'country' => 'BR'],
    ]));
    network('POST', '/api/network/events', capturePayload('aut_bruno_secret', ['id' => 'evt_bruno_secret', 'amount_cents' => 4_321]));

    $this->ana = User::query()->where('email', 'ana@acme.test')->sole();
});

dataset('bruno secrets', ['aut_bruno_secret', 'evt_bruno_secret', 'Livraria do Bruno', 'R$ 43,21', 'tok_bruno', 'Bruno']);

it('never shows another cardholder data on the page', function (string $secret): void {
    $this->actingAs($this->ana)
        ->get('/my-card')
        ->assertOk()
        ->assertSee('aut_ana_1')
        ->assertDontSee($secret);
})->with('bruno secrets');

it('ignores query parameters that point to another card', function (string $query): void {
    $this->actingAs($this->ana)
        ->get('/my-card?'.$query)
        ->assertOk()
        ->assertSee('Cartão tok_ana')
        ->assertDontSee('aut_bruno_secret');
})->with([
    'card token' => 'card=tok_bruno',
    'card id' => fn (): string => 'card_id='.Card::query()->where('token', 'tok_bruno')->value('id'),
    'user id' => fn (): string => 'user_id='.User::query()->where('email', 'bruno@acme.test')->value('id'),
    'card id and user id' => fn (): string => 'card_id='.Card::query()->where('token', 'tok_bruno')->value('id').'&user_id='.User::query()->where('email', 'bruno@acme.test')->value('id'),
    'purchase id' => 'purchase=aut_bruno_secret',
    'page of the purchases' => 'page=2',
]);

it('keeps every page inside the logged-in card', function (string $method, array $arguments): void {
    Livewire::actingAs($this->ana)
        ->test(MyCard::class)
        ->call($method, ...$arguments)
        ->assertDontSee('aut_bruno_secret')
        ->assertDontSee('Livraria do Bruno')
        ->assertViewHas('purchases', fn ($page): bool => collect($page->items())->every(fn (Purchase $purchase): bool => $purchase->card_id === $this->ana->card?->id));
})->with([
    'next page' => ['nextPage', []],
    'previous page' => ['previousPage', []],
    'far page' => ['gotoPage', [99]],
    'negative page' => ['gotoPage', [-1]],
    'text page' => ['gotoPage', ['aut_bruno_secret']],
    'other page name' => ['gotoPage', [1, 'card_id']],
    'set page' => ['setPage', [2]],
]);

it('refuses Livewire calls to methods that are not public actions', function (string $method): void {
    $bruno = Purchase::query()->where('network_authorization_id', 'aut_bruno_secret')->sole();

    Livewire::actingAs($this->ana)
        ->test(MyCard::class)
        ->call($method, $bruno->id);
})->with(['historyOf', 'purchases', 'card', 'monthLabel'])->throws(MethodNotFoundException::class);

it('keeps no card or purchase in its public state', function (): void {
    $public = array_map(
        fn (ReflectionProperty $property): string => $property->getName(),
        new ReflectionClass(MyCard::class)->getProperties(ReflectionProperty::IS_PUBLIC),
    );

    expect($public)->toBe(['paginators']);
});
