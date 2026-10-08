<?php

declare(strict_types=1);

namespace App\Support;

final class Money
{
    public static function format(int $cents): string
    {
        $formatted = 'R$ '.number_format(abs($cents) / 100, 2, ',', '.');

        return $cents < 0 ? '-'.$formatted : $formatted;
    }
}
