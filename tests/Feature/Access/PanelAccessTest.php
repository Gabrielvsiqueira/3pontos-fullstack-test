<?php

declare(strict_types=1);

use App\Models\User;
use Passa\Ledger\Models\Card;

it('lets the manager into the panel', function (): void {
    $this->actingAs(User::factory()->manager()->create())
        ->get('/admin')
        ->assertOk();
});

it('forbids cardholders from the panel', function (): void {
    $card = Card::factory()->create();

    $this->actingAs($card->user)
        ->get('/admin')
        ->assertForbidden();
});

it('seeds the Acme cards from the challenge', function (): void {
    $this->seed();

    $cards = Card::query()->with('user')->orderBy('id')->get();

    expect($cards->pluck('token')->all())->toBe(['tok_ana', 'tok_bruno', 'tok_carla', 'tok_diego'])
        ->and($cards->pluck('monthly_limit_cents')->all())->toBe([200_000, 50_000, 100_000, 5_000_000])
        ->and($cards->pluck('purchase_limit_cents')->all())->toBe([80_000, null, null, null])
        ->and($cards->pluck('blocked')->all())->toBe([false, false, true, false])
        ->and($cards->first()->blocked_mccs)->toBe(['7995'])
        ->and(User::query()->where('email', 'marina@acme.test')->sole()->isManager())->toBeTrue();
});
