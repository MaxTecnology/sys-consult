<?php

namespace App\Filament\Resources\DteMessageResource\Pages;

use App\Filament\Resources\DteMessageResource;
use App\Models\DteMessageEvent;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewDteMessage extends ViewRecord
{
    protected static string $resource = DteMessageResource::class;

    public function mount($record): void
    {
        parent::mount($record);

        $record = $this->getRecord();
        $user = Auth::user();

        // Apenas na primeira visualização registramos quem viu e criamos evento.
        if ($user && !$record->visualizado_por) {
            $record->fill([
                'visualizado_por' => $user->id,
                'visualizado_em' => now(),
                'primeira_visualizacao_em' => now(),
                'ultima_interacao_em' => now(),
                'status_interno' => $record->status_interno === 'novo' ? 'em_andamento' : $record->status_interno,
            ]);
            $record->save();

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
    }
}
