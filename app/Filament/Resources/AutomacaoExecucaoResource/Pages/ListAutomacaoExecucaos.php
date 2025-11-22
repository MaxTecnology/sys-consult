<?php

namespace App\Filament\Resources\AutomacaoExecucaoResource\Pages;

use App\Filament\Resources\AutomacaoExecucaoResource;
use Filament\Resources\Pages\ListRecords;

class ListAutomacaoExecucaos extends ListRecords
{
    protected static string $resource = AutomacaoExecucaoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
