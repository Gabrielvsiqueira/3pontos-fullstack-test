<?php

declare(strict_types=1);

use App\Enums\TransactionType;
use App\Ledger\Actions\RecordDeposit;
use App\Models\Company;
use App\Models\Purchase;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('records a deposit as one ledger line and updates the company balance', function (): void {
    $company = Company::factory()->create();

    $deposit = resolve(RecordDeposit::class)->handle($company, 50_000);

    expect($company->refresh()->balance_cents)->toBe(50_000)
        ->and($company->held_cents)->toBe(0)
        ->and($deposit->transaction)->not->toBeNull()
        ->and($deposit->transaction->type)->toBe(TransactionType::Deposit)
        ->and($deposit->transaction->balance_delta_cents)->toBe(50_000)
        ->and($deposit->transaction->limit_delta_cents)->toBe(0);
});

it('refuses updates to transactions in the model', function (): void {
    $deposit = resolve(RecordDeposit::class)->handle(Company::factory()->create(), 1_000);

    $deposit->transaction->update(['reference' => 'changed']);
})->throws(LogicException::class, 'append-only');

it('refuses updates and deletes to transactions in the database', function (string $statement): void {
    resolve(RecordDeposit::class)->handle(Company::factory()->create(), 1_000);

    DB::statement($statement);
})->with([
    'update' => "UPDATE transactions SET reference = 'changed'",
    'delete' => 'DELETE FROM transactions',
])->throws(QueryException::class, 'append-only');

it('allows only one transaction per source message', function (): void {
    $deposit = resolve(RecordDeposit::class)->handle(Company::factory()->create(), 1_000);

    $deposit->transaction()->create([
        'company_id' => $deposit->company_id,
        'type' => TransactionType::Deposit,
        'reference' => 'dep_again',
        'occurred_at' => now(),
        'limit_delta_cents' => 0,
        'balance_delta_cents' => 1_000,
        'held_delta_cents' => 0,
    ]);
})->throws(UniqueConstraintViolationException::class);

it('keeps one purchase per network authorization id', function (): void {
    Purchase::query()->create(['network_authorization_id' => 'aut_1']);
    Purchase::query()->create(['network_authorization_id' => 'aut_1']);
})->throws(UniqueConstraintViolationException::class);

it('rejects a non-positive deposit', function (): void {
    resolve(RecordDeposit::class)->handle(Company::factory()->create(), 0);
})->throws(InvalidArgumentException::class);

it('does not mutate the ledger when the seed runs twice', function (): void {
    $this->seed();
    $this->seed();

    expect(Company::query()->sole()->balance_cents)->toBe(1_000_000)
        ->and(Transaction::query()->count())->toBe(1);
});

it('reads network times back exactly as they were written', function (): void {
    $occurredAt = CarbonImmutable::parse('2026-09-17T14:03:22Z');

    $deposit = resolve(RecordDeposit::class)->handle(Company::factory()->create(), 1_000, occurredAt: $occurredAt);

    expect(DB::selectOne('show timezone')->TimeZone)->toBe('UTC')
        ->and($deposit->refresh()->occurred_at->equalTo($occurredAt))->toBeTrue()
        ->and($deposit->transaction->occurred_at->utc()->format('Y-m-d\TH:i:s\Z'))->toBe('2026-09-17T14:03:22Z');
});
