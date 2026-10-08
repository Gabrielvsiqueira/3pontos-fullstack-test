<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Passa\Network\Http\Controllers\AuthorizationController;
use Passa\Network\Http\Controllers\CardAvailableController;
use Passa\Network\Http\Controllers\CardStatementController;
use Passa\Network\Http\Controllers\EventController;
use Passa\Network\Http\Middleware\VerifyNetworkSignature;

Route::prefix('api/network')
    ->middleware(['api', VerifyNetworkSignature::class])
    ->group(function (): void {
        Route::post('authorizations', AuthorizationController::class);
        Route::post('events', EventController::class);
        Route::get('cards/{cardToken}/available', CardAvailableController::class);
        Route::get('cards/{cardToken}/statement', CardStatementController::class);
    });
