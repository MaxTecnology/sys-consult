<?php

namespace App\Filament\Widgets;

use App\Models\DteMessage;
use App\Models\Empresa;
use App\Models\Certificado;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DteOpsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $mensagensCriticas = DteMessage::where('requere_atencao', true)->count();
        $mensagensNaoLidas = DteMessage::where('lida_sefaz', false)->count();

        $empresasSemConsulta = Empresa::where(function ($q) {
            $q->whereNull('ultima_consulta_api')
              ->orWhere('ultima_consulta_api', '<', now()->subDays(7));
        })->count();

        $certificadosVencendo = Certificado::ativos()
            ->whereNotNull('validade')
            ->where('validade', '<=', now()->addDays(30))
            ->count();

        return [
            Stat::make('Mensagens críticas', $mensagensCriticas)
                ->description('requere_atencao = true')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($mensagensCriticas > 0 ? 'danger' : 'success'),

            Stat::make('Mensagens não lidas', $mensagensNaoLidas)
                ->description('Sem leitura na SEFAZ')
                ->descriptionIcon('heroicon-m-envelope-open')
                ->color($mensagensNaoLidas > 0 ? 'warning' : 'success'),

            Stat::make('Empresas sem consulta 7d', $empresasSemConsulta)
                ->description('Data última consulta > 7 dias ou nula')
                ->descriptionIcon('heroicon-m-building-office')
                ->color($empresasSemConsulta > 0 ? 'warning' : 'success'),

            Stat::make('Certificados vencendo', $certificadosVencendo)
                ->description('Próximos 30 dias')
                ->descriptionIcon('heroicon-m-key')
                ->color($certificadosVencendo > 0 ? 'danger' : 'success'),
        ];
    }
}
