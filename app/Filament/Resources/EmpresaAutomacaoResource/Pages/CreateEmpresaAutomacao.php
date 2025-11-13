<?php

namespace App\Filament\Resources\EmpresaAutomacaoResource\Pages;

use App\Filament\Resources\EmpresaAutomacaoResource;
use App\Models\EmpresaAutomacao;
use Filament\Resources\Pages\CreateRecord;

class CreateEmpresaAutomacao extends CreateRecord
{
    protected static string $resource = EmpresaAutomacaoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Calcular próxima execução baseada nas configurações
        $automacao = new EmpresaAutomacao($data);
        $data['proxima_execucao'] = $automacao->calcularProximaExecucao();
        $data['status'] = $data['ativa'] ? 'ativa' : 'desabilitada';
        $data['criado_por'] = auth()->id();

        return $data;
    }
}
