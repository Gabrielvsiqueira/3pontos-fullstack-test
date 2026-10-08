<?php

declare(strict_types=1);

namespace Passa\Admin\Filament\Resources\Purchases;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Passa\Admin\Filament\Resources\Purchases\Pages\ListPurchases;
use Passa\Admin\Filament\Resources\Purchases\Pages\ViewPurchase;
use Passa\Admin\Filament\Resources\Purchases\Schemas\PurchaseInfolist;
use Passa\Admin\Filament\Resources\Purchases\Tables\PurchasesTable;
use Passa\Ledger\Models\Purchase;

final class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $recordTitleAttribute = 'network_authorization_id';

    protected static ?string $modelLabel = 'compra';

    protected static ?string $pluralModelLabel = 'compras';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return PurchaseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchasesTable::configure($table);
    }

    /**
     * @return Builder<Purchase>
     */
    public static function getEloquentQuery(): Builder
    {
        return Purchase::query()
            ->with(['card.user', 'authorization', 'captures', 'cancellation'])
            ->withSum('captures', 'amount_cents');
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

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'view' => ViewPurchase::route('/{record}'),
        ];
    }
}
