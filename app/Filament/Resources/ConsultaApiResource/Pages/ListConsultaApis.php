<?php

namespace App\Filament\Resources\ConsultaApiResource\Pages;

use App\Filament\Resources\ConsultaApiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsultaApis extends ListRecords
{
    protected static string $resource = ConsultaApiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
