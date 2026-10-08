<?php

declare(strict_types=1);

use App\Cards\Livewire\Login;
use App\Cards\Livewire\MyCard;
use App\Http\Controllers\Cards\LogoutController;
use App\Http\Middleware\EnsureUserHasCard;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::livewire('/login', Login::class)->middleware('guest')->name('login');
Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');
Route::livewire('/my-card', MyCard::class)->middleware(['auth', EnsureUserHasCard::class])->name('my-card');
