<?php

declare(strict_types=1);

use App\Cards\Livewire\Login;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed();
    RateLimiter::clear('ana@acme.test|127.0.0.1');
});

it('renders the login form', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('entre no seu cartão')
        ->assertSeeLivewire(Login::class);
});

it('logs a cardholder in and sends them to their card', function (): void {
    Livewire::test(Login::class)
        ->set('email', 'ana@acme.test')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('my-card'));

    $this->assertAuthenticatedAs(User::query()->where('email', 'ana@acme.test')->sole());
});

it('rejects wrong credentials with a generic message', function (string $email, string $password): void {
    Livewire::test(Login::class)
        ->set('email', $email)
        ->set('password', $password)
        ->call('login')
        ->assertHasErrors(['email' => 'E-mail ou senha inválidos.'])
        ->assertSet('password', '');

    $this->assertGuest();
})->with([
    'wrong password' => ['ana@acme.test', 'wrong'],
    'unknown email' => ['nobody@acme.test', 'password'],
]);

it('validates the form fields', function (): void {
    Livewire::test(Login::class)
        ->set('email', 'not-an-email')
        ->set('password', '')
        ->call('login')
        ->assertHasErrors(['email', 'password']);
});

it('locks the login after five failed attempts', function (): void {
    $login = Livewire::test(Login::class)->set('email', 'ana@acme.test');

    foreach (range(1, Login::MAX_ATTEMPTS) as $attempt) {
        $login->set('password', 'wrong')->call('login');
    }

    $login->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email')
        ->assertSee('Muitas tentativas');

    $this->assertGuest();
});

it('sends guests from my-card to the login', function (): void {
    $this->get('/my-card')->assertRedirect('/login');
});

it('shows my-card to a cardholder', function (): void {
    $this->actingAs(User::query()->where('email', 'bruno@acme.test')->sole())
        ->get('/my-card')
        ->assertOk()
        ->assertSee('Olá, Bruno')
        ->assertSee('tok_bruno');
});

it('forbids my-card to a user without a card', function (): void {
    $this->actingAs(User::query()->where('email', 'marina@acme.test')->sole())
        ->get('/my-card')
        ->assertForbidden();
});

it('sends a logged in cardholder from the login to their card', function (): void {
    $this->actingAs(User::query()->where('email', 'ana@acme.test')->sole())
        ->get('/login')
        ->assertRedirect(route('my-card'));
});

it('logs out and invalidates the session', function (): void {
    $this->actingAs(User::query()->where('email', 'ana@acme.test')->sole())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
