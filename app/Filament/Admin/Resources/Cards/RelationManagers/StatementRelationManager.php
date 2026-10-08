<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\RelationManagers;

use App\Enums\TransactionType;
use App\Ledger\BillingMonth;
use App\Ledger\CardStatement;
use App\Models\Card;
use App\Models\Transaction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

final class StatementRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Statement';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        $card = $this->getOwnerRecord();
        throw_unless($card instanceof Card, LogicException::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->select('transactions.*')
                ->selectRaw('? + (select sum(running.limit_delta_cents) from transactions as running where running.card_id = transactions.card_id and running.month = transactions.month and running.id <= transactions.id) as limit_remaining_after_cents', [$card->monthly_limit_cents])
                ->orderBy('id'))
            ->description(fn (): string => $this->summary($card))
            ->columns([
                TextColumn::make('occurred_at')->label('Data')->dateTime('d/m/Y H:i:s', BillingMonth::TIMEZONE),
                TextColumn::make('type')->label('Tipo')->badge()->formatStateUsing(fn (TransactionType $state): string => $state->value),
                TextColumn::make('reference')->label('Referência')->copyable(),
                TextColumn::make('limit_delta_cents')->label('Valor')->money('BRL', divideBy: 100, locale: 'pt_BR'),
                TextColumn::make('limit_remaining_after_cents')->label('Limite restante')->money('BRL', divideBy: 100, locale: 'pt_BR'),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->label('Mês')
                    ->options(fn (): array => $this->monthOptions($card))
                    ->default(BillingMonth::of(now()))
                    ->selectablePlaceholder(false),
            ])
            ->paginated(false)
            ->emptyStateHeading('Nenhuma transação feita neste mês');
    }

    private function selectedMonth(): string
    {
        $month = $this->getTableFilterState('month')['value'] ?? null;

        return is_string($month) && $month !== '' ? $month : BillingMonth::of(now());
    }

    private function summary(Card $card): string
    {
        $statement = resolve(CardStatement::class)->for($card, $this->selectedMonth());

        return sprintf(
            'Limite do mês R$ %s · limite restante R$ %s',
            number_format($statement['limit_cents'] / 100, 2, ',', '.'),
            number_format($statement['limit_remaining_cents'] / 100, 2, ',', '.'),
        );
    }

    /**
     * @return array<string, string>
     */
    private function monthOptions(Card $card): array
    {
        $months = Transaction::query()
            ->where('card_id', $card->id)
            ->whereNotNull('month')
            ->distinct()
            ->pluck('month')
            ->push(BillingMonth::of(now()))
            ->unique()
            ->sortDesc()
            ->values();

        return $months->mapWithKeys(fn (string $month): array => [$month => $month])->all();
    }
}
