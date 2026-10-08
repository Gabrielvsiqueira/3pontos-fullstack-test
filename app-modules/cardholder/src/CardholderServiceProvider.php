<?php

declare(strict_types=1);

namespace Passa\Cardholder;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Passa\Cardholder\Livewire\Login;
use Passa\Cardholder\Livewire\MyCard;

final class CardholderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cardholder');

        Livewire::component('cardholder.login', Login::class);
        Livewire::component('cardholder.my-card', MyCard::class);
    }
}
