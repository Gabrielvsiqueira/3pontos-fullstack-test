<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case Authorization = 'authorization';
    case Capture = 'capture';
    case Cancellation = 'cancellation';
    case Deposit = 'deposit';
}
