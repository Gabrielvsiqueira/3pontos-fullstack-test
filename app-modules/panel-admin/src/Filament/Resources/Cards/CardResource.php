<?php

declare(strict_types=1);

namespace Passa\Admin\Filament\Resources\Cards;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Passa\Admin\Filament\Resources\Cards\Pages\ListCards;
use Passa\Admin\Filament\Resources\Cards\Pages\ViewCard;
use Passa\Admin\Filament\Resources\Cards\RelationManagers\StatementRelationManager;
use Passa\Admin\Filament\Resources\Cards\Schemas\CardInfolist;
use Passa\Admin\Filament\Resources\Cards\Tables\CardsTable;
use Passa\Ledger\Models\Card;

final class CardResource extends Resource
{
    protected static ?string $model = Card::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $recordTitleAttribute = 'token';

    protected static ?string $modelLabel = 'cartão';

    protected static ?string $pluralModelLabel = 'cartões';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return CardInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CardsTable::configure($table);
    }

    /**
     * @return Builder<Card>
     */
    public static function getEloquentQuery(): Builder
    {
        return Card::query()->with(['user', 'company']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            StatementRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCards::route('/'),
            'view' => ViewCard::route('/{record}'),
        ];
    }
}
