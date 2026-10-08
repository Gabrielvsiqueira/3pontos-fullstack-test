<?php

declare(strict_types=1);

namespace Passa\Admin\Filament\Resources\Cards\Pages;

use Filament\Resources\Pages\ViewRecord;
use Passa\Admin\Filament\Resources\Cards\CardResource;

final class ViewCard extends ViewRecord
{
    protected static string $resource = CardResource::class;
}
