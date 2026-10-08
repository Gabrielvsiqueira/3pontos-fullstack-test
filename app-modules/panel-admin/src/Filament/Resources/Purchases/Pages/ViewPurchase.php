<?php

declare(strict_types=1);

namespace Passa\Admin\Filament\Resources\Purchases\Pages;

use Filament\Resources\Pages\ViewRecord;
use Passa\Admin\Filament\Resources\Purchases\PurchaseResource;

final class ViewPurchase extends ViewRecord
{
    protected static string $resource = PurchaseResource::class;
}
