<?php

declare(strict_types=1);

namespace Passa\Admin\Filament\Resources\Purchases\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Passa\Admin\Filament\PurchaseFlagLabels;
use Passa\Ledger\Models\Purchase;
use Passa\Ledger\PurchaseHistory;

final class PurchaseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Compra')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('network_authorization_id')->label('authorization_id')->copyable(),
                        TextEntry::make('card.user.name')->label('Titular do cartão')->placeholder('aguardando authorization'),
                        TextEntry::make('month')->label('Mês')->placeholder('—'),
                        TextEntry::make('authorization.amount_cents')->label('Autorizado')->money('BRL', divideBy: 100, locale: 'pt_BR')->placeholder('—'),
                        TextEntry::make('captures_sum_amount_cents')->label('Capturado')->money('BRL', divideBy: 100, locale: 'pt_BR')->placeholder('—'),
                        TextEntry::make('held_cents')->label('Reserva')->money('BRL', divideBy: 100, locale: 'pt_BR'),
                        IconEntry::make('closed')->label('Fechada')->boolean(),
                        TextEntry::make('flags')
                            ->label('Sinais')
                            ->state(fn (Purchase $record): array => PurchaseFlagLabels::of($record))
                            ->badge()
                            ->color('warning')
                            ->placeholder('—'),
                    ]),
                Section::make('História')
                    ->schema([
                        RepeatableEntry::make('history')
                            ->hiddenLabel()
                            ->state(fn (Purchase $record): array => PurchaseHistory::of($record))
                            ->table([
                                TableColumn::make('Quando'),
                                TableColumn::make('Mensagem'),
                                TableColumn::make('Detalhes'),
                                TableColumn::make('id da rede'),
                            ])
                            ->schema([
                                TextEntry::make('occurred_at')->hiddenLabel()->dateTime('d/m/Y H:i:s', 'America/Sao_Paulo'),
                                TextEntry::make('message')->hiddenLabel()->badge(),
                                TextEntry::make('details')->hiddenLabel(),
                                TextEntry::make('reference')->hiddenLabel(),
                            ]),
                    ]),
            ]);
    }
}
