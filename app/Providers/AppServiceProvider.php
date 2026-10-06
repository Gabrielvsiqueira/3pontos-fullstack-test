<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerTelescope();
        $this->registerDebugbar();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureCommands();
        $this->configureDatabase();
        $this->configureDates();
        $this->configureVite();
        $this->configureUrl();
    }

    /**
     * Configure the application's commands.
     */
    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // `serve` ignores PHP_CLI_SERVER_WORKERS unless reloading is off, so the
        // default `composer dev` would run a single worker and serialize requests.
        DevCommands::artisan('serve --no-reload', 'server');
    }

    /**
     * Configure the application's models.
     *
     * Strict mode throws on lazy loading, silently discarded attributes and
     * access to missing attributes. Keep it on: it surfaces N+1 early.
     */
    private function configureDatabase(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Configure the application's Vite.
     */
    private function configureVite(): void
    {
        Vite::useAggressivePrefetching();
    }

    /**
     * Configure the dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Configure the application's URL.
     */
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
