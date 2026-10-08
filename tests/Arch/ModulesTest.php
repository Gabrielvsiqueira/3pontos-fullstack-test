<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * @return list<string>
 */
function filamentNamespaces(): array
{
    $psr4 = require dirname(__DIR__, 2).'/vendor/composer/autoload_psr4.php';
    $namespaces = [];

    foreach ($psr4 as $prefix => $paths) {
        if (! str_starts_with((string) $prefix, 'Filament\\')) {
            continue;
        }

        if ($prefix !== 'Filament\\') {
            $namespaces[] = mb_rtrim((string) $prefix, '\\');

            continue;
        }

        foreach (Finder::create()->depth(0)->in($paths) as $entry) {
            $namespaces[] = 'Filament\\'.$entry->getBasename('.php');
        }
    }

    return array_values(array_unique($namespaces));
}

arch('the ledger depends on no other module')
    ->expect('Passa\Ledger')
    ->not->toUse(['Passa\Authorization', 'Passa\Network', 'Passa\Cardholder', 'Passa\Admin']);

arch('the authorization depends only on the ledger')
    ->expect('Passa\Authorization')
    ->not->toUse(['Passa\Network', 'Passa\Cardholder', 'Passa\Admin']);

arch('the network depends only on the authorization and the ledger')
    ->expect('Passa\Network')
    ->not->toUse(['Passa\Cardholder', 'Passa\Admin']);

arch('the cardholder area depends only on the ledger')
    ->expect('Passa\Cardholder')
    ->not->toUse(['Passa\Authorization', 'Passa\Network', 'Passa\Admin']);

arch('the admin panel depends only on the ledger')
    ->expect('Passa\Admin')
    ->not->toUse(['Passa\Authorization', 'Passa\Network', 'Passa\Cardholder']);

arch('the ledger knows nothing about HTTP or the interface')
    ->expect('Passa\Ledger')
    ->not->toUse(['Illuminate\Http', 'Symfony\Component\HttpFoundation', 'Livewire', ...filamentNamespaces()]);

arch('the authorization knows nothing about HTTP or the interface')
    ->expect('Passa\Authorization')
    ->not->toUse(['Illuminate\Http', 'Symfony\Component\HttpFoundation', 'Livewire', ...filamentNamespaces()]);

arch('the cardholder area uses no Filament code')
    ->expect('Passa\Cardholder')
    ->not->toUse(filamentNamespaces());

arch('the kernel only reaches the ledger')
    ->expect('App')
    ->not->toUse(['Passa\Authorization', 'Passa\Network', 'Passa\Cardholder', 'Passa\Admin']);

it('lists the Filament namespaces it guards against', function (): void {
    expect(filamentNamespaces())->toContain('Filament\Notifications', 'Filament\Tables', 'Filament\Resources', 'Filament\Facades');
});

it('renders the cardholder area without Filament components', function (): void {
    $views = Finder::create()->files()->in(dirname(__DIR__, 2).'/app-modules/cardholder/resources/views')->name('*.blade.php');

    expect($views)->not->toBeEmpty();

    foreach ($views as $view) {
        expect(mb_strtolower($view->getContents()))->not->toContain('filament');
    }
});
