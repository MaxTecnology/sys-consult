<?php

namespace App\Filament\Resources\EmpresaAutomacaoResource\Pages;

use App\Filament\Resources\EmpresaAutomacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmpresaAutomacao extends EditRecord
{
    protected static string $resource = EmpresaAutomacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

}
