<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Enums\TransactionType;
use App\Models\Cancellation;
use App\Models\Capture;
use App\Models\Company;
use App\Models\Purchase;

final readonly class PurchaseMovements
{
    public function __construct(private Ledger $ledger) {}

    public function hasClosingEvent(Purchase $purchase): bool
    {
        return $purchase->captures()->where('final', true)->exists()
            || $purchase->cancellation()->exists();
    }

    public function applyPending(Purchase $purchase, Company $company): void
    {
        $events = $purchase->captures()->get()
            ->concat($purchase->cancellation()->get())
            ->sortBy([
                fn (Capture|Cancellation $a, Capture|Cancellation $b): int => $a->occurred_at <=> $b->occurred_at,
                fn (Capture|Cancellation $a, Capture|Cancellation $b): int => $a->id <=> $b->id,
            ]);

        foreach ($events as $event) {
            $event instanceof Capture
                ? $this->applyCapture($purchase, $company, $event)
                : $this->applyCancellation($purchase, $company, $event);
        }
    }

    public function applyCapture(Purchase $purchase, Company $company, Capture $capture): void
    {
        $consumed = min($capture->amount_cents, $purchase->held_cents);
        $released = $capture->final ? $purchase->held_cents - $consumed : 0;

        $purchase->captured_cents += $capture->amount_cents;
        $purchase->held_cents -= $consumed + $released;
        $purchase->closed = $purchase->closed || $capture->final;
        $purchase->save();

        $this->ledger->post(
            source: $capture,
            company: $company,
            type: TransactionType::Capture,
            reference: $capture->network_id,
            occurredAt: $capture->occurred_at,
            limitDelta: -($capture->amount_cents - $consumed) + $released,
            balanceDelta: -$capture->amount_cents,
            heldDelta: -($consumed + $released),
            purchase: $purchase,
        );
    }

    public function applyCancellation(Purchase $purchase, Company $company, Cancellation $cancellation): void
    {
        $released = $purchase->held_cents;

        $purchase->held_cents = 0;
        $purchase->closed = true;
        $purchase->save();

        if ($released === 0) {
            return;
        }

        $this->ledger->post(
            source: $cancellation,
            company: $company,
            type: TransactionType::Cancellation,
            reference: $cancellation->network_id,
            occurredAt: $cancellation->occurred_at,
            limitDelta: $released,
            heldDelta: -$released,
            purchase: $purchase,
        );
    }
}
