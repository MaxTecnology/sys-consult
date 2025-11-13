<?php

namespace App\Filament\Resources\ConsultaApiResource\Pages;

use App\Filament\Resources\ConsultaApiResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewConsultaApi extends ViewRecord
{
    protected static string $resource = ConsultaApiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
