<?php

declare(strict_types=1);

namespace Passa\Network;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

final class NetworkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/network.php', 'network');
    }

    public function boot(): void
    {
        $isNetwork = fn (Request $request): bool => $request->is('api/network/*');

        TrimStrings::skipWhen($isNetwork);
        ConvertEmptyStringsToNull::skipWhen($isNetwork);
    }
}
