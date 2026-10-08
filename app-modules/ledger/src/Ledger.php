<?php

declare(strict_types=1);

namespace Passa\Ledger;

use Carbon\CarbonInterface;
use Passa\Ledger\Enums\TransactionType;
use Passa\Ledger\Models\Authorization;
use Passa\Ledger\Models\Cancellation;
use Passa\Ledger\Models\Capture;
use Passa\Ledger\Models\Card;
use Passa\Ledger\Models\CardMonth;
use Passa\Ledger\Models\Company;
use Passa\Ledger\Models\Deposit;
use Passa\Ledger\Models\Purchase;
use Passa\Ledger\Models\Transaction;

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

    public function availableFor(Card $card, Company $company, string $month): int
    {
        if ($card->blocked) {
            return 0;
        }

        return max(0, min($this->limitRemaining($card, $month), $company->availableCents()));
    }
}
