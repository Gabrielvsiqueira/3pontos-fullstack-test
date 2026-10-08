<?php

declare(strict_types=1);

namespace Passa\Ledger\Console\Commands;

use Illuminate\Console\Command;
use Passa\Ledger\Actions\RebuildProjections;

final class RebuildLedgerCommand extends Command
{
    protected $signature = 'ledger:rebuild {--check : Only report projections that differ from the ledger}';

    protected $description = 'Rebuild the company, card-month and purchase projections from the ledger';

    public function handle(RebuildProjections $rebuild): int
    {
        $check = (bool) $this->option('check');
        $drift = $rebuild->handle(write: ! $check);

        if ($drift === []) {
            $this->info('Projections match the ledger.');

            return self::SUCCESS;
        }

        foreach ($drift as $line) {
            $this->line($line);
        }

        if ($check) {
            $this->error(count($drift).' projection(s) differ from the ledger.');

            return self::FAILURE;
        }

        $this->info(count($drift).' projection(s) rebuilt from the ledger.');

        return self::SUCCESS;
    }
}
