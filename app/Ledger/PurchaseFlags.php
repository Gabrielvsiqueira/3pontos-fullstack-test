<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Enums\Decision;
use App\Models\Authorization;
use App\Models\Cancellation;
use App\Models\Card;
use App\Models\Purchase;

final readonly class PurchaseFlags
{
    public const array MARGIN_MCCS = ['5812', '7011', '7512'];

    public function recompute(Purchase $purchase): void
    {
        $authorization = Authorization::query()->where('purchase_id', $purchase->id)->first();
        $cancellation = Cancellation::query()->where('purchase_id', $purchase->id)->first();
        $card = $purchase->card_id === null ? null : Card::query()->find($purchase->card_id);
        $captured = (int) $purchase->captures()->sum('amount_cents');

        $purchase->over_capture = $authorization instanceof Authorization
            && $this->isAboveMargin($captured, $authorization);

        $purchase->over_purchase_limit = $card?->purchase_limit_cents !== null
            && $captured > $card->purchase_limit_cents;

        $purchase->captured_when_declined = $authorization?->decision === Decision::Declined
            && $captured > 0;

        $purchase->captured_after_cancellation = $cancellation instanceof Cancellation
            && $purchase->captures()->where('occurred_at', '>', $cancellation->occurred_at)->exists();

        $purchase->save();
    }

    private function isAboveMargin(int $captured, Authorization $authorization): bool
    {
        return in_array($authorization->mcc, self::MARGIN_MCCS, true)
            ? $captured * 100 > $authorization->amount_cents * 120
            : $captured > $authorization->amount_cents;
    }
}
