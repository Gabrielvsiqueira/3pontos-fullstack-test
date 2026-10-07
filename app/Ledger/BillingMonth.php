<?php

declare(strict_types=1);

namespace App\Ledger;

use Carbon\CarbonInterface;

final class BillingMonth
{
    public const string TIMEZONE = 'America/Sao_Paulo';

    public static function of(CarbonInterface $moment): string
    {
        return $moment->avoidMutation()->setTimezone(self::TIMEZONE)->format('Y-m');
    }
}
