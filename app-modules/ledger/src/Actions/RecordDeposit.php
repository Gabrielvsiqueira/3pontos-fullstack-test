<?php

declare(strict_types=1);

namespace Passa\Ledger\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Passa\Ledger\Enums\TransactionType;
use Passa\Ledger\Ledger;
use Passa\Ledger\Models\Company;
use Passa\Ledger\Models\Deposit;

final readonly class RecordDeposit
{
    public function __construct(private Ledger $ledger) {}

    public function handle(Company $company, int $amountCents, ?User $user = null, ?CarbonImmutable $occurredAt = null): Deposit
    {
        throw_if($amountCents < 1, InvalidArgumentException::class, 'Deposit amount must be positive.');

        return DB::transaction(function () use ($company, $amountCents, $user, $occurredAt): Deposit {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);

            $deposit = $company->deposits()->create([
                'user_id' => $user?->id,
                'amount_cents' => $amountCents,
                'occurred_at' => $occurredAt ?? CarbonImmutable::now(),
            ]);

            $this->ledger->post(
                source: $deposit,
                company: $company,
                type: TransactionType::Deposit,
                reference: 'dep_'.$deposit->id,
                occurredAt: $deposit->occurred_at,
                balanceDelta: $amountCents,
            );

            return $deposit;
        });
    }
}
