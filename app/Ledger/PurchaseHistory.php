<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Models\Purchase;
use App\Support\Money;
use Carbon\CarbonImmutable;

final class PurchaseHistory
{
    /**
     * @return list<array{occurred_at: CarbonImmutable, message: string, details: string, reference: string}>
     */
    public static function of(Purchase $purchase): array
    {
        $entries = [];

        if ($purchase->authorization !== null) {
            $authorization = $purchase->authorization;
            $entries[] = [
                'occurred_at' => $authorization->occurred_at,
                'message' => 'authorization',
                'details' => mb_trim(sprintf(
                    '%s %s · %s · MCC %s · %s',
                    $authorization->decision->value,
                    $authorization->reason->value ?? '',
                    Money::format($authorization->amount_cents),
                    $authorization->mcc,
                    $authorization->merchant_name,
                )),
                'reference' => $purchase->network_authorization_id,
            ];
        }

        foreach ($purchase->captures as $capture) {
            $entries[] = [
                'occurred_at' => $capture->occurred_at,
                'message' => 'capture',
                'details' => sprintf('%s · sequence %d%s', Money::format($capture->amount_cents), $capture->sequence, $capture->final ? ' · final' : ''),
                'reference' => $capture->network_id,
            ];
        }

        if ($purchase->cancellation !== null) {
            $entries[] = [
                'occurred_at' => $purchase->cancellation->occurred_at,
                'message' => 'cancellation',
                'details' => '',
                'reference' => $purchase->cancellation->network_id,
            ];
        }

        usort($entries, fn (array $a, array $b): int => $a['occurred_at'] <=> $b['occurred_at']);

        return $entries;
    }
}
