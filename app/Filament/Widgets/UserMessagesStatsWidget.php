<?php

namespace App\Filament\Widgets;

use App\Models\DteMessage;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class UserMessagesStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth()->user();
        return $user && ($user->isAdmin() || $user->empresas()->exists());
    }

    protected function getCards(): array
    {
        $total = DteMessage::count();
        $naoLidas = DteMessage::where('lida_sefaz', false)->count();
        $novo = DteMessage::where('status_interno', 'novo')->count();
        $emAndamento = DteMessage::where('status_interno', 'em_andamento')->count();
        $concluido = DteMessage::where('status_interno', 'concluido')->count();

        return [
            Card::make('Mensagens', $total)->description('Total das empresas vinculadas'),
            Card::make('Não lidas', $naoLidas)->description('Lida SEFAZ = Não'),
            Card::make('Em andamento', $emAndamento)->description('Status interno em_andamento'),
            Card::make('Concluídas', $concluido)->description('Status interno concluido'),
            Card::make('Novas', $novo)->description('Status interno novo'),
        ];
    }
}
