<?php

declare(strict_types=1);

namespace App\Authorization\Actions;

use App\Authorization\AuthorizationResult;
use App\Authorization\DeclineRules;
use App\Enums\Decision;
use App\Enums\DeclineReason;
use App\Enums\TransactionType;
use App\Ledger\BillingMonth;
use App\Ledger\Ledger;
use App\Ledger\Purchases;
use App\Models\Authorization;
use App\Models\Card;
use App\Models\Company;
use App\Network\Messages\AuthorizationMessage;
use Illuminate\Support\Facades\DB;

final readonly class AuthorizePurchase
{
    public function __construct(
        private DeclineRules $rules,
        private Ledger $ledger,
        private Purchases $purchases,
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

            if ($decision === Decision::Approved && $company instanceof Company) {
                $purchase->held_cents = $message->amountCents;
                $purchase->save();

                $this->ledger->post(
                    source: $authorization,
                    company: $company,
                    type: TransactionType::Authorization,
                    reference: $message->id,
                    occurredAt: $message->occurredAt,
                    limitDelta: -$message->amountCents,
                    heldDelta: $message->amountCents,
                    purchase: $purchase,
                );
            } else {
                $purchase->save();
            }

            return AuthorizationResult::from($authorization);
        });
    }
}
