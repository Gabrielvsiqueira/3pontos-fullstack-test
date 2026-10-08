<?php

declare(strict_types=1);

namespace Passa\Ledger;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Passa\Ledger\Models\Authorization;
use Passa\Ledger\Models\Cancellation;
use Passa\Ledger\Models\Capture;
use Passa\Ledger\Models\Deposit;

final class LedgerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'authorization' => Authorization::class,
            'capture' => Capture::class,
            'cancellation' => Cancellation::class,
            'deposit' => Deposit::class,
        ]);
    }
}
