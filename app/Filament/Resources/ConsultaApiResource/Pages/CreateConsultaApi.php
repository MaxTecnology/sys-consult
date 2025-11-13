<?php

namespace App\Filament\Resources\ConsultaApiResource\Pages;

use App\Filament\Resources\ConsultaApiResource;
use App\Models\Empresa;
use App\Models\Certificado;
use App\Services\InfoSimplesService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateConsultaApi extends CreateRecord
{
    protected static string $resource = ConsultaApiResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $empresa = Empresa::find($data['empresa_id']);
        $certificado = Certificado::find($data['certificado_id']);

        if (!$empresa->inscricao_estadual) {
            Notification::make()
                ->title('Empresa sem Inscrição Estadual')
                ->body('A empresa selecionada não possui inscrição estadual cadastrada.')
                ->danger()
                ->send();

            $this->halt();
        }

        $service = new InfoSimplesService();

        try {
            $consulta = $service->consultarEmpresaCaixaPostal($empresa, $certificado);

            if ($consulta->sucesso) {
                Notification::make()
                    ->title('Consulta realizada com sucesso!')
                    ->body("Código: {$consulta->response_code} - {$consulta->code_message}")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Consulta retornou erro')
                    ->body("Código: {$consulta->response_code} - {$consulta->code_message}")
                    ->warning()
                    ->send();
            }

            return $consulta;

        } catch (\Exception $e) {
            Notification::make()
                ->title('Erro ao realizar consulta')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }
}

