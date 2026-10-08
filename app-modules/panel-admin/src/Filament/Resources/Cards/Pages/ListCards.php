<?php

declare(strict_types=1);

namespace Passa\Admin\Filament\Resources\Cards\Pages;

use Filament\Resources\Pages\ListRecords;
use Passa\Admin\Filament\Resources\Cards\CardResource;

final class ListCards extends ListRecords
{
    protected static string $resource = CardResource::class;
}
