<?php

declare(strict_types=1);

namespace App\Authorization;

use Passa\Ledger\Enums\DeclineReason;
use Passa\Ledger\Models\Card;
use Passa\Ledger\Models\Company;

final readonly class DeclineRules
{
    public function firstFailing(?Card $card, ?Company $company, string $mcc, int $amountCents, int $limitRemainingCents): ?DeclineReason
    {
        return match (true) {
            ! $card instanceof Card || ! $company instanceof Company => DeclineReason::CardNotFound,
            $card->blocked => DeclineReason::CardBlocked,
            in_array($mcc, $card->blocked_mccs, true) => DeclineReason::MccBlocked,
            $card->purchase_limit_cents !== null && $amountCents > $card->purchase_limit_cents => DeclineReason::AmountOverPurchaseLimit,
            $amountCents > $limitRemainingCents => DeclineReason::MonthlyLimitExceeded,
            $amountCents > $company->availableCents() => DeclineReason::InsufficientFunds,
            default => null,
        };
    }
}
