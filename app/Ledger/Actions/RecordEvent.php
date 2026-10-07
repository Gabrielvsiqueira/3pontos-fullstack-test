<?php

declare(strict_types=1);

namespace App\Ledger\Actions;

use App\Enums\EventOutcome;
use App\Exceptions\PurchaseChangedWhileLocking;
use App\Ledger\PurchaseFlags;
use App\Ledger\PurchaseMovements;
use App\Ledger\Purchases;
use App\Models\Cancellation;
use App\Models\Capture;
use App\Models\Card;
use App\Models\Company;
use App\Models\Purchase;
use App\Network\Messages\CancellationMessage;
use App\Network\Messages\CaptureMessage;
use Illuminate\Support\Facades\DB;

final readonly class RecordEvent
{
    private const int LOCK_ATTEMPTS = 3;

    public function __construct(
        private Purchases $purchases,
        private PurchaseMovements $movements,
        private PurchaseFlags $flags,
    ) {}

    public function handle(CaptureMessage|CancellationMessage $message): EventOutcome
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): EventOutcome => $this->record($message));
            } catch (PurchaseChangedWhileLocking $exception) {
                throw_if($attempt >= self::LOCK_ATTEMPTS, $exception);
            }
        }
    }

    private function record(CaptureMessage|CancellationMessage $message): EventOutcome
    {
        $cardId = Purchase::query()
            ->where('network_authorization_id', $message->authorizationId)
            ->value('card_id');

        $company = null;

        if ($cardId !== null) {
            $companyId = Card::query()->whereKey($cardId)->value('company_id');
            $company = Company::query()->lockForUpdate()->findOrFail($companyId);
            Card::query()->lockForUpdate()->findOrFail($cardId);
        }

        $purchase = $this->purchases->lockOrCreate($message->authorizationId);

        throw_if($purchase->card_id !== $cardId, PurchaseChangedWhileLocking::class);

        return $message instanceof CaptureMessage
            ? $this->recordCapture($purchase, $company, $message)
            : $this->recordCancellation($purchase, $company, $message);
    }

    private function recordCapture(Purchase $purchase, ?Company $company, CaptureMessage $message): EventOutcome
    {
        if (Capture::query()->where('network_id', $message->id)->exists()) {
            return EventOutcome::AlreadyReceived;
        }

        if (Cancellation::query()->where('network_id', $message->id)->exists()) {
            return EventOutcome::Conflict;
        }

        $original = $purchase->captures()->where('sequence', $message->sequence)->first();

        if ($original instanceof Capture) {
            return $this->isSameCapture($original, $message) ? EventOutcome::AlreadyReceived : EventOutcome::Conflict;
        }

        $capture = $purchase->captures()->create([
            'network_id' => $message->id,
            'sequence' => $message->sequence,
            'amount_cents' => $message->amountCents,
            'currency' => $message->currency,
            'final' => $message->final,
            'occurred_at' => $message->occurredAt,
            'payload' => $message->payload,
        ]);

        if ($company instanceof Company) {
            $this->movements->applyCapture($purchase, $company, $capture);
        }

        $this->flags->recompute($purchase);

        return EventOutcome::Accepted;
    }

    private function recordCancellation(Purchase $purchase, ?Company $company, CancellationMessage $message): EventOutcome
    {
        if (Cancellation::query()->where('network_id', $message->id)->exists()) {
            return EventOutcome::AlreadyReceived;
        }

        if (Capture::query()->where('network_id', $message->id)->exists()) {
            return EventOutcome::Conflict;
        }

        $original = $purchase->cancellation()->first();

        if ($original instanceof Cancellation) {
            return $original->occurred_at->equalTo($message->occurredAt) ? EventOutcome::AlreadyReceived : EventOutcome::Conflict;
        }

        $cancellation = $purchase->cancellation()->create([
            'network_id' => $message->id,
            'occurred_at' => $message->occurredAt,
            'payload' => $message->payload,
        ]);

        if ($company instanceof Company) {
            $this->movements->applyCancellation($purchase, $company, $cancellation);
        }

        $this->flags->recompute($purchase);

        return EventOutcome::Accepted;
    }

    private function isSameCapture(Capture $original, CaptureMessage $message): bool
    {
        return $original->occurred_at->equalTo($message->occurredAt)
            && $original->amount_cents === $message->amountCents
            && $original->currency === $message->currency
            && $original->final === $message->final;
    }
}
