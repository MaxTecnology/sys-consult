<?php

namespace App\Filament\Widgets;

use App\Models\DteMailbox;
use App\Models\DteMessage;
use App\Models\Certificado;
use App\Models\EmpresaAutomacao;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DteAlertsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $mailboxesAtivas = DteMailbox::count();
        $mensagensNaoLidas = DteMessage::where('lida_sefaz', false)->count();
        $mensagensAtrasadas = DteMessage::where('requere_atencao', true)
            ->where(function ($query) {
                $query->whereNull('status_interno')
                    ->orWhere('status_interno', 'novo');
            })
            ->count();

        $certificadosVencendo = Certificado::ativos()
            ->whereBetween('validade', [now(), now()->addDays(30)])
            ->count();

        $automacoesErro = EmpresaAutomacao::where('status', 'erro')->count();

        return [
            Stat::make('Mensagens não lidas', $mensagensNaoLidas)
                ->description($mailboxesAtivas . ' caixas monitoradas')
                ->descriptionIcon('heroicon-m-square-3-stack-3d')
                ->color($mensagensNaoLidas > 0 ? 'warning' : 'success'),

            Stat::make('Mensagens críticas', $mensagensAtrasadas)
                ->description('Requerem atenção interna')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($mensagensAtrasadas > 0 ? 'danger' : 'success'),

            Stat::make('Certificados vencendo', $certificadosVencendo)
                ->description('Próximos 30 dias')
                ->descriptionIcon('heroicon-m-key')
                ->color($certificadosVencendo > 0 ? 'warning' : 'success'),

            Stat::make('Automações com erro', $automacoesErro)
                ->description('Jobs pausados / aguardando revisão')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->color($automacoesErro > 0 ? 'danger' : 'success'),
        ];
    }
}
