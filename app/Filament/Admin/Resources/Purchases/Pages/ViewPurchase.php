<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Purchases\Pages;

use App\Filament\Admin\Resources\Purchases\PurchaseResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;
}
