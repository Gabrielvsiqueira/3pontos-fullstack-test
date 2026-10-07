<?php

declare(strict_types=1);

namespace App\Ledger\Actions;

use App\Ledger\PurchaseFlags;
use App\Models\CardMonth;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class RebuildProjections
{
    public function __construct(private PurchaseFlags $flags) {}

    /**
     * @return list<string>
     */
    public function handle(bool $write = true): array
    {
        DB::beginTransaction();

        try {
            Company::query()->lockForUpdate()->orderBy('id')->get();

            $drift = [
                ...$this->companies(),
                ...$this->cardMonths(),
                ...$this->purchases(),
            ];
        } catch (Throwable $throwable) {
            DB::rollBack();

            throw $throwable;
        }

        $write ? DB::commit() : DB::rollBack();

        return $drift;
    }

    /**
     * @return list<string>
     */
    private function companies(): array
    {
        $drift = [];

        foreach (Company::query()->orderBy('id')->get() as $company) {
            $balance = (int) Transaction::query()->where('company_id', $company->id)->sum('balance_delta_cents');
            $held = (int) Transaction::query()->where('company_id', $company->id)->sum('held_delta_cents');

            if ($company->balance_cents !== $balance || $company->held_cents !== $held) {
                $drift[] = "company {$company->id}: balance {$company->balance_cents} → {$balance}, held {$company->held_cents} → {$held}";
                $company->balance_cents = $balance;
                $company->held_cents = $held;
                $company->save();
            }
        }

        return $drift;
    }

    /**
     * @return list<string>
     */
    private function cardMonths(): array
    {
        $expected = Transaction::query()
            ->whereNotNull('card_id')
            ->whereNotNull('month')
            ->groupBy('card_id', 'month')
            ->selectRaw('card_id, month, sum(limit_delta_cents) as total')
            ->get()
            ->mapWithKeys(fn (Transaction $row): array => ["{$row->card_id}|{$row->month}" => (int) $row->getAttribute('total')]);

        $current = CardMonth::query()->get()->keyBy(fn (CardMonth $row): string => "{$row->card_id}|{$row->month}");

        $drift = [];

        foreach ($expected->keys()->merge($current->keys())->unique() as $key) {
            $should = $expected->get($key, 0);
            $is = $current->get($key)->limit_delta_cents ?? 0;

            if ($should === $is) {
                continue;
            }

            $drift[] = "card month {$key}: limit delta {$is} → {$should}";

            [$cardId, $month] = explode('|', (string) $key);
            CardMonth::query()->updateOrCreate(['card_id' => (int) $cardId, 'month' => $month], ['limit_delta_cents' => $should]);
        }

        return $drift;
    }

    /**
     * @return list<string>
     */
    private function purchases(): array
    {
        $drift = [];

        foreach (Purchase::query()->orderBy('id')->lazy() as $purchase) {
            $transactions = Transaction::query()->where('purchase_id', $purchase->id);
            $before = $purchase->only(['held_cents', 'captured_cents', 'closed', 'over_capture', 'over_purchase_limit', 'captured_when_declined', 'captured_after_cancellation']);

            $purchase->held_cents = (int) $transactions->clone()->sum('held_delta_cents');
            $purchase->captured_cents = -(int) $transactions->clone()->where('type', 'capture')->sum('balance_delta_cents');
            $purchase->closed = $purchase->card_id !== null
                && ($purchase->captures()->where('final', true)->exists() || $purchase->cancellation()->exists());

            $this->flags->recompute($purchase);

            $after = $purchase->only(array_keys($before));

            if ($before !== $after) {
                $drift[] = "purchase {$purchase->network_authorization_id}: ".json_encode(array_diff_assoc($after, $before));
            }
        }

        return $drift;
    }
}
