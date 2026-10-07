<?php

declare(strict_types=1);

use App\Http\Controllers\Network\AuthorizationController;
use App\Http\Controllers\Network\CardAvailableController;
use App\Http\Controllers\Network\CardStatementController;
use App\Http\Controllers\Network\EventController;
use App\Http\Middleware\VerifyNetworkSignature;
use Illuminate\Support\Facades\Route;

Route::prefix('network')
    ->middleware(VerifyNetworkSignature::class)
    ->group(function (): void {
        Route::post('authorizations', AuthorizationController::class);
        Route::post('events', EventController::class);
        Route::get('cards/{cardToken}/available', CardAvailableController::class);
        Route::get('cards/{cardToken}/statement', CardStatementController::class);
    });
