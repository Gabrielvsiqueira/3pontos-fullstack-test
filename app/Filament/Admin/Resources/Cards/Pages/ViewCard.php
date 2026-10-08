<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Pages;

use App\Filament\Admin\Resources\Cards\CardResource;
use Filament\Resources\Pages\ViewRecord;

final class ViewCard extends ViewRecord
{
    protected static string $resource = CardResource::class;
}
