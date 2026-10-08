<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Passa\Cardholder\Http\Controllers\LogoutController;
use Passa\Cardholder\Http\Middleware\EnsureUserHasCard;
use Passa\Cardholder\Livewire\Login;
use Passa\Cardholder\Livewire\MyCard;

Route::middleware('web')->group(function (): void {
    Route::livewire('/login', Login::class)->middleware('guest')->name('login');
    Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');
    Route::livewire('/my-card', MyCard::class)->middleware(['auth', EnsureUserHasCard::class])->name('my-card');
});
