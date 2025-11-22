<?php

namespace App\Filament\Resources\DteMessageResource\Pages;

use App\Filament\Resources\DteMessageResource;
use App\Models\DteMessageEvent;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewDteMessage extends ViewRecord
{
    protected static string $resource = DteMessageResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;
        $user = Auth::user();

        if ($user) {
            $record->update([
                'visualizado_por' => $user->id,
                'visualizado_em' => now(),
                'primeira_visualizacao_em' => $record->primeira_visualizacao_em ?? now(),
                'ultima_interacao_em' => now(),
                'status_interno' => $record->status_interno === 'novo' ? 'em_andamento' : $record->status_interno,
            ]);

            DteMessageEvent::create([
                'dte_message_id' => $record->id,
                'user_id' => $user->id,
                'tipo_evento' => 'visualizado',
                'descricao' => 'Mensagem visualizada no painel',
                'payload' => [
                    'request_id' => request()->header('X-Request-Id'),
                ],
                'registrado_em' => now(),
            ]);
        }

        return parent::mutateFormDataBeforeFill($data);
    }
}
