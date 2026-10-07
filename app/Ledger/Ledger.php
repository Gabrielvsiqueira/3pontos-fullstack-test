<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Enums\TransactionType;
use App\Models\Authorization;
use App\Models\Cancellation;
use App\Models\Capture;
use App\Models\Card;
use App\Models\CardMonth;
use App\Models\Company;
use App\Models\Deposit;
use App\Models\Purchase;
use App\Models\Transaction;
use Carbon\CarbonInterface;

final readonly class Ledger
{
    public function post(
        Authorization|Capture|Cancellation|Deposit $source,
        Company $company,
        TransactionType $type,
        string $reference,
        CarbonInterface $occurredAt,
        int $limitDelta = 0,
        int $balanceDelta = 0,
        int $heldDelta = 0,
        ?Purchase $purchase = null,
    ): Transaction {
        $transaction = $source->transaction()->create([
            'company_id' => $company->id,
            'card_id' => $purchase?->card_id,
            'purchase_id' => $purchase?->id,
            'month' => $purchase?->month,
            'type' => $type,
            'reference' => $reference,
            'occurred_at' => $occurredAt,
            'limit_delta_cents' => $limitDelta,
            'balance_delta_cents' => $balanceDelta,
            'held_delta_cents' => $heldDelta,
        ]);

        if ($balanceDelta !== 0 || $heldDelta !== 0) {
            $company->balance_cents += $balanceDelta;
            $company->held_cents += $heldDelta;
            $company->save();
        }

        if ($limitDelta !== 0 && $purchase?->card_id !== null && $purchase->month !== null) {
            CardMonth::query()
                ->firstOrCreate(['card_id' => $purchase->card_id, 'month' => $purchase->month])
                ->increment('limit_delta_cents', $limitDelta);
        }

        return $transaction;
    }

    public function limitRemaining(Card $card, string $month): int
    {
        $delta = CardMonth::query()
            ->where('card_id', $card->id)
            ->where('month', $month)
            ->value('limit_delta_cents');

        return $card->monthly_limit_cents + (int) $delta;
    }
}
