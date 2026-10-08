<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerTelescope();
        $this->registerDebugbar();
    }

    public function boot(): void
    {
        $this->configureCommands();
        $this->configureDatabase();
        $this->configureDates();
        $this->configureVite();
        $this->configureUrl();
    }

    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
        DevCommands::artisan('serve --no-reload', 'server');
    }

    private function configureDatabase(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Relation::enforceMorphMap([
            'user' => User::class,
        ]);
    }

    private function configureVite(): void
    {
        Vite::useAggressivePrefetching();
    }

    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    private function configureUrl(): void
    {
        URL::forceHttps($this->app->isProduction());
    }

    private function registerTelescope(): void
    {
        if ($this->app->isLocal() && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    private function registerDebugbar(): void
    {
        if ($this->app->isLocal()
            && $this->app->hasDebugModeEnabled()
            && class_exists(\Fruitcake\LaravelDebugbar\ServiceProvider::class)
            && config('debugbar.enabled')
        ) {
            $this->app->register(\Fruitcake\LaravelDebugbar\ServiceProvider::class);
        }
    }
}
