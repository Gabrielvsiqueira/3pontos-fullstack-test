<?php

declare(strict_types=1);

namespace Passa\Authorization\Actions;

use Illuminate\Support\Facades\DB;
use Passa\Authorization\AuthorizationResult;
use Passa\Authorization\DeclineRules;
use Passa\Authorization\DTOs\AuthorizationMessage;
use Passa\Ledger\BillingMonth;
use Passa\Ledger\Enums\Decision;
use Passa\Ledger\Enums\DeclineReason;
use Passa\Ledger\Enums\TransactionType;
use Passa\Ledger\Ledger;
use Passa\Ledger\Models\Authorization;
use Passa\Ledger\Models\Card;
use Passa\Ledger\Models\Company;
use Passa\Ledger\PurchaseFlags;
use Passa\Ledger\PurchaseMovements;
use Passa\Ledger\Purchases;

final readonly class AuthorizePurchase
{
    public function __construct(
        private DeclineRules $rules,
        private Ledger $ledger,
        private Purchases $purchases,
        private PurchaseMovements $movements,
        private PurchaseFlags $flags,
    ) {}

    public function handle(AuthorizationMessage $message): AuthorizationResult
    {
        return DB::transaction(function () use ($message): AuthorizationResult {
            $cardId = Card::query()->where('token', $message->cardToken)->value('id');
            $card = null;
            $company = null;

            if ($cardId !== null) {
                $companyId = Card::query()->whereKey($cardId)->value('company_id');
                $company = Company::query()->lockForUpdate()->findOrFail($companyId);
                $card = Card::query()->lockForUpdate()->findOrFail($cardId);
            }

            $purchase = $this->purchases->lockOrCreate($message->id);

            $existing = Authorization::query()->where('purchase_id', $purchase->id)->first();

            if ($existing !== null) {
                return AuthorizationResult::from($existing);
            }

            $month = BillingMonth::of($message->occurredAt);

            $reason = $this->rules->firstFailing(
                card: $card,
                company: $company,
                mcc: $message->mcc,
                amountCents: $message->amountCents,
                limitRemainingCents: $card instanceof Card ? $this->ledger->limitRemaining($card, $month) : 0,
            );

            $decision = $reason instanceof DeclineReason ? Decision::Declined : Decision::Approved;

            $authorization = $purchase->authorization()->create([
                'card_token' => $message->cardToken,
                'amount_cents' => $message->amountCents,
                'currency' => $message->currency,
                'mcc' => $message->mcc,
                'merchant_name' => $message->merchantName,
                'merchant_city' => $message->merchantCity,
                'merchant_country' => $message->merchantCountry,
                'occurred_at' => $message->occurredAt,
                'decision' => $decision,
                'reason' => $reason,
                'payload' => $message->payload,
            ]);

            $purchase->card_id = $card?->id;
            $purchase->month = $month;
            $purchase->authorized_cents = $message->amountCents;

            $hold = $decision === Decision::Approved && ! $this->movements->hasClosingEvent($purchase)
                ? $message->amountCents
                : 0;

            $purchase->held_cents = $hold;
            $purchase->save();

            if ($company instanceof Company) {
                if ($hold > 0) {
                    $this->ledger->post(
                        source: $authorization,
                        company: $company,
                        type: TransactionType::Authorization,
                        reference: $message->id,
                        occurredAt: $message->occurredAt,
                        limitDelta: -$hold,
                        heldDelta: $hold,
                        purchase: $purchase,
                    );
                }

                $this->movements->applyPending($purchase, $company);
            }

            $this->flags->recompute($purchase);

            return AuthorizationResult::from($authorization);
        });
    }
}
