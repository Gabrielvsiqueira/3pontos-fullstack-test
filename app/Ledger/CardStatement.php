<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Models\Card;
use App\Models\Transaction;

final readonly class CardStatement
{
    public const string TIME_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * @return array{month: string, limit_cents: int, limit_remaining_cents: int, transactions: list<array{occurred_at: string, type: string, amount_cents: int, reference: string, limit_remaining_after_cents: int}>}
     */
    public function for(Card $card, string $month): array
    {
        $remaining = $card->monthly_limit_cents;
        $lines = [];

        $transactions = Transaction::query()
            ->where('card_id', $card->id)
            ->where('month', $month)
            ->orderBy('id')
            ->get();

        foreach ($transactions as $transaction) {
            $remaining += $transaction->limit_delta_cents;

            $lines[] = [
                'occurred_at' => $transaction->occurred_at->utc()->format(self::TIME_FORMAT),
                'type' => $transaction->type->value,
                'amount_cents' => $transaction->limit_delta_cents,
                'reference' => $transaction->reference,
                'limit_remaining_after_cents' => $remaining,
            ];
        }

        return [
            'month' => $month,
            'limit_cents' => $card->monthly_limit_cents,
            'limit_remaining_cents' => $remaining,
            'transactions' => $lines,
        ];
    }
}
