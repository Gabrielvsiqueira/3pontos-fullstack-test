<?php

declare(strict_types=1);

namespace Passa\Ledger\Enums;

enum TransactionType: string
{
    case Authorization = 'authorization';
    case Capture = 'capture';
    case Cancellation = 'cancellation';
    case Deposit = 'deposit';
}
