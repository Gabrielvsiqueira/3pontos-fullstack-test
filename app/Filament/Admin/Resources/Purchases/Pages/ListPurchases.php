<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases\Pages;

use App\Enums\Decision;
use App\Filament\Admin\Resources\Purchases\PurchaseResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

final class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas'),
            'declined' => Tab::make('Recusadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereRelation('authorization', 'decision', Decision::Declined)),
            'flagged' => Tab::make('Sinalizadas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(fn (Builder $flags): Builder => $flags
                    ->where('over_capture', true)
                    ->orWhere('over_purchase_limit', true)
                    ->orWhere('captured_when_declined', true)
                    ->orWhere('captured_after_cancellation', true))),
            'waiting' => Tab::make('Events sem authorization')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->doesntHave('authorization')),
        ];
    }
}
