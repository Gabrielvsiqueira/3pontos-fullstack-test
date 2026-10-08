<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Passa\Ledger\BillingMonth;
use Passa\Ledger\Ledger;
use Passa\Ledger\Models\Card;

final class CardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cartão')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')->label('Titular do cartão'),
                        TextEntry::make('token')->label('Token'),
                        IconEntry::make('blocked')->label('Bloqueado')->boolean(),
                        TextEntry::make('monthly_limit_cents')->label('Limite mensal')->money('BRL', divideBy: 100, locale: 'pt_BR'),
                        TextEntry::make('purchase_limit_cents')->label('Teto por compra')->money('BRL', divideBy: 100, locale: 'pt_BR')->placeholder('—'),
                        TextEntry::make('blocked_mccs')->label('MCC bloqueados')->badge()->placeholder('—'),
                    ]),
                Section::make('Mês corrente')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('limit_remaining')
                            ->label('Limite restante')
                            ->state(fn (Card $record): int => resolve(Ledger::class)->limitRemaining($record, BillingMonth::of(now())))
                            ->money('BRL', divideBy: 100, locale: 'pt_BR'),
                        TextEntry::make('available')
                            ->label('Disponível')
                            ->state(fn (Card $record): int => resolve(Ledger::class)->availableFor($record, $record->company, BillingMonth::of(now())))
                            ->money('BRL', divideBy: 100, locale: 'pt_BR'),
                    ]),
            ]);
    }
}
