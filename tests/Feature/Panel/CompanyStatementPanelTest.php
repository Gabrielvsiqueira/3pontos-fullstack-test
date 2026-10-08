<?php

declare(strict_types=1);

use App\Filament\Admin\Pages\CompanyStatement;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Passa\Ledger\Enums\TransactionType;
use Passa\Ledger\Models\Company;
use Passa\Ledger\Models\Deposit;
use Passa\Ledger\Models\Transaction;

beforeEach(function (): void {
    $this->seed();
    $this->travelTo('2026-09-17 15:00:00');

    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_1', 'amount_cents' => 30_000]));
    network('POST', '/api/network/events', capturePayload('aut_1', ['id' => 'evt_1', 'amount_cents' => 10_000, 'final' => false]));
    network('POST', '/api/network/authorizations', authorizationPayload(['id' => 'aut_2', 'amount_cents' => 5_000]));
    network('POST', '/api/network/events', cancellationPayload('aut_2', ['id' => 'evt_cancel']));

    $this->marina = User::query()->where('email', 'marina@acme.test')->sole();
    $this->actingAs($this->marina);
    Filament::setCurrentPanel('admin');
});

it('lists deposits and captures with the balance after each one', function (): void {
    $lines = Transaction::query()->whereIn('type', [TransactionType::Deposit, TransactionType::Capture])->get();

    Livewire::test(CompanyStatement::class)
        ->assertOk()
        ->assertCanSeeTableRecords($lines)
        ->assertCanNotSeeTableRecords(Transaction::query()->whereIn('type', [TransactionType::Authorization, TransactionType::Cancellation])->get())
        ->assertTableColumnStateSet('balance_after_cents', 1_000_000, $lines->firstWhere('type', TransactionType::Deposit))
        ->assertTableColumnStateSet('balance_after_cents', 990_000, $lines->firstWhere('reference', 'evt_1'));
});

it('shows the current balance, hold and available balance', function (): void {
    Livewire::test(CompanyStatement::class)
        ->assertSee('Saldo R$ 9.900,00 · reservado R$ 200,00 · saldo disponível R$ 9.700,00');
});

it('records a deposit from the panel', function (string $input, int $cents): void {
    Livewire::test(CompanyStatement::class)
        ->callAction('deposit', ['amount' => $input])
        ->assertHasNoActionErrors()
        ->assertNotified('Depósito registrado.');

    $deposit = Deposit::query()->latest('id')->first();

    expect($deposit->amount_cents)->toBe($cents)
        ->and($deposit->user_id)->toBe($this->marina->id)
        ->and($deposit->transaction->balance_delta_cents)->toBe($cents)
        ->and(Company::query()->sole()->balance_cents)->toBe(990_000 + $cents);
})->with([
    'whole reais' => ['1500', 150_000],
    'comma decimals' => ['1500,50', 150_050],
    'dot decimals' => ['1500.5', 150_050],
    'one cent' => ['0,01', 1],
]);

it('rejects invalid deposit amounts', function (string $input): void {
    Livewire::test(CompanyStatement::class)
        ->callAction('deposit', ['amount' => $input])
        ->assertHasActionErrors(['amount']);

    expect(Deposit::query()->count())->toBe(1);
})->with(['empty' => '', 'text' => 'abc', 'negative' => '-50', 'three decimals' => '10,999', 'thousand separator' => '1.500,00']);

it('rejects a zero deposit', function (): void {
    Livewire::test(CompanyStatement::class)
        ->callAction('deposit', ['amount' => '0,00'])
        ->assertNotified('O depósito precisa ser maior que zero.');

    expect(Deposit::query()->count())->toBe(1);
});

it('lets a new deposit raise what the cards can spend', function (): void {
    Livewire::test(CompanyStatement::class)->callAction('deposit', ['amount' => '10000']);

    network('GET', '/api/network/cards/tok_diego/available')
        ->assertJsonPath('available_cents', 1_970_000);
});

it('forbids cardholders from the company statement', function (): void {
    $this->actingAs(User::query()->where('email', 'bruno@acme.test')->sole());

    $this->get(CompanyStatement::getUrl())->assertForbidden();
});
