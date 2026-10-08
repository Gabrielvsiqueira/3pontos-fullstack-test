<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\TransactionType;
use App\Ledger\Actions\RecordDeposit;
use App\Models\Company;
use App\Models\Transaction;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CompanyStatement extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'Statement da empresa';

    protected static ?string $title = 'Statement da empresa';

    protected static ?string $slug = 'company-statement';

    protected static ?int $navigationSort = 3;

    public static function toCents(string $amount): int
    {
        [$reais, $centavos] = array_pad(explode('.', str_replace(',', '.', $amount), 2), 2, '0');

        return (int) $reais * 100 + (int) mb_str_pad($centavos, 2, '0');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Transaction::query()
                ->where('company_id', $this->company()->id)
                ->where('balance_delta_cents', '!=', 0)
                ->select('transactions.*')
                ->selectRaw('(select sum(running.balance_delta_cents) from transactions as running where running.company_id = transactions.company_id and running.id <= transactions.id) as balance_after_cents')
                ->orderBy('id'))
            ->heading('Depósitos e captures')
            ->description(fn (): string => $this->summary())
            ->columns([
                TextColumn::make('occurred_at')->label('Data')->dateTime('d/m/Y H:i:s', 'America/Sao_Paulo'),
                TextColumn::make('type')->label('Tipo')->badge()->formatStateUsing(fn (TransactionType $state): string => $state->value),
                TextColumn::make('reference')->label('Referência'),
                TextColumn::make('balance_delta_cents')->label('Valor')->money('BRL', divideBy: 100, locale: 'pt_BR'),
                TextColumn::make('balance_after_cents')->label('Saldo após')->money('BRL', divideBy: 100, locale: 'pt_BR'),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Nenhum depósito ou capture');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('deposit')
                ->label('Registrar depósito')
                ->icon(Heroicon::OutlinedBanknotes)
                ->modalHeading('Registrar depósito')
                ->schema([
                    TextInput::make('amount')
                        ->label('Valor (R$)')
                        ->required()
                        ->regex('/^\d{1,9}([.,]\d{1,2})?$/')
                        ->placeholder('1000,00')
                        ->validationMessages(['regex' => 'Informe um valor como 1000,00.']),
                ])
                ->action(function (array $data, RecordDeposit $recordDeposit): void {
                    $cents = self::toCents((string) $data['amount']);

                    if ($cents < 1) {
                        Notification::make()->title('O depósito precisa ser maior que zero.')->danger()->send();

                        return;
                    }

                    $user = auth()->user();
                    $recordDeposit->handle($this->company(), $cents, $user instanceof User ? $user : null);

                    Notification::make()->title('Depósito registrado.')->success()->send();
                }),
        ];
    }

    private function company(): Company
    {
        $user = auth()->user();

        return Company::query()->findOrFail($user instanceof User ? $user->company_id : null);
    }

    private function summary(): string
    {
        $company = $this->company();
        $money = fn (int $cents): string => 'R$ '.number_format($cents / 100, 2, ',', '.');

        return sprintf(
            'Saldo %s · reservado %s · saldo disponível %s',
            $money($company->balance_cents),
            $money($company->held_cents),
            $money($company->availableCents()),
        );
    }
}
