<?php

namespace App\Filament\Resources\DteMessageResource\Pages;

use App\Filament\Resources\DteMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListDteMessages extends ListRecords
{
    protected static string $resource = DteMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
