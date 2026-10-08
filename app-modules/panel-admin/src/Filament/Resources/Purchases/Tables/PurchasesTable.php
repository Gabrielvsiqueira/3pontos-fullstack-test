<?php

declare(strict_types=1);

namespace Passa\Admin\Filament\Resources\Purchases\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Passa\Admin\Filament\PurchaseFlagLabels;
use Passa\Ledger\Enums\Decision;
use Passa\Ledger\Enums\DeclineReason;
use Passa\Ledger\Models\Purchase;

final class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('network_authorization_id')->label('authorization_id')->searchable()->copyable(),
                TextColumn::make('card.user.name')->label('Titular do cartão')->placeholder('—'),
                TextColumn::make('authorization.decision')
                    ->label('Decisão')
                    ->badge()
                    ->formatStateUsing(fn (Decision $state): string => $state->value)
                    ->color(fn (Decision $state): string => $state === Decision::Approved ? 'success' : 'danger')
                    ->placeholder('aguardando authorization'),
                TextColumn::make('authorization.reason')
                    ->label('reason')
                    ->formatStateUsing(fn (DeclineReason $state): string => $state->value)
                    ->placeholder('—'),
                TextColumn::make('authorization.mcc')->label('MCC')->placeholder('—'),
                TextColumn::make('authorization.amount_cents')->label('Autorizado')->money('BRL', divideBy: 100, locale: 'pt_BR')->placeholder('—'),
                TextColumn::make('captures_sum_amount_cents')->label('Capturado')->money('BRL', divideBy: 100, locale: 'pt_BR')->placeholder('—'),
                TextColumn::make('held_cents')->label('Reserva')->money('BRL', divideBy: 100, locale: 'pt_BR'),
                TextColumn::make('flags')
                    ->label('Sinais')
                    ->state(fn (Purchase $record): array => PurchaseFlagLabels::of($record))
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),
                TextColumn::make('authorization.occurred_at')->label('Data')->dateTime('d/m/Y H:i', 'America/Sao_Paulo')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
