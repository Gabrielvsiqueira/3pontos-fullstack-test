<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Tables;

use App\Ledger\BillingMonth;
use App\Ledger\Ledger;
use App\Models\Card;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

final class CardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Titular do cartão')->searchable(),
                TextColumn::make('token')->label('Token')->searchable(),
                IconColumn::make('blocked')->label('Bloqueado')->boolean(),
                TextColumn::make('monthly_limit_cents')->label('Limite mensal')->money('BRL', divideBy: 100, locale: 'pt_BR'),
                TextColumn::make('purchase_limit_cents')->label('Teto por compra')->money('BRL', divideBy: 100, locale: 'pt_BR')->placeholder('—'),
                TextColumn::make('limit_remaining')
                    ->label('Limite restante')
                    ->state(fn (Card $record): int => resolve(Ledger::class)->limitRemaining($record, BillingMonth::of(now())))
                    ->money('BRL', divideBy: 100, locale: 'pt_BR')
                    ->color(fn (int $state): ?string => $state < 0 ? 'danger' : null),
                TextColumn::make('available')
                    ->label('Disponível')
                    ->state(fn (Card $record): int => resolve(Ledger::class)->availableFor($record, $record->company, BillingMonth::of(now())))
                    ->money('BRL', divideBy: 100, locale: 'pt_BR'),
            ])
            ->defaultSort('id')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
