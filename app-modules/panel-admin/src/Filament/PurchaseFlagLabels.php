<?php

declare(strict_types=1);

namespace Passa\Admin\Filament;

use Passa\Ledger\Models\Purchase;

final class PurchaseFlagLabels
{
    /**
     * @return list<string>
     */
    public static function of(Purchase $purchase): array
    {
        return array_keys(array_filter([
            'over_capture' => $purchase->over_capture,
            'over_purchase_limit' => $purchase->over_purchase_limit,
            'captured_when_declined' => $purchase->captured_when_declined,
            'captured_after_cancellation' => $purchase->captured_after_cancellation,
        ]));
    }
}
