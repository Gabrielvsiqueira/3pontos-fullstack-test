<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Cards\Pages;

use App\Filament\Admin\Resources\Cards\CardResource;
use Filament\Resources\Pages\ListRecords;

final class ListCards extends ListRecords
{
    protected static string $resource = CardResource::class;
}
