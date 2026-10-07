<?php

declare(strict_types=1);

namespace App\Enums;

enum DeclineReason: string
{
    case CardNotFound = 'card_not_found';
    case CardBlocked = 'card_blocked';
    case MccBlocked = 'mcc_blocked';
    case AmountOverPurchaseLimit = 'amount_over_purchase_limit';
    case MonthlyLimitExceeded = 'monthly_limit_exceeded';
    case InsufficientFunds = 'insufficient_funds';
}
