<?php

// Criar o arquivo: app/Filament/Widgets/ConsultasStatsWidget.php

namespace App\Filament\Widgets;

use App\Models\ConsultaApi;
use App\Models\Empresa;
use App\Models\Certificado;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ConsultasStatsWidget extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $consultasHoje = ConsultaApi::whereDate('consultado_em', today())->count();
        $consultasSucesso = ConsultaApi::whereDate('consultado_em', today())->where('sucesso', true)->count();
        $consultasErro = ConsultaApi::whereDate('consultado_em', today())->where('sucesso', false)->count();
        $gastoHoje = ConsultaApi::whereDate('consultado_em', today())->sum('preco');

        $empresasAtivas = Empresa::ativas()->count();
        $certificadosAtivos = Certificado::ativos()->count();
        $certificadosVencendo = Certificado::ativos()
            ->whereBetween('validade', [now(), now()->addDays(30)])
            ->count();

        return [
            Stat::make('Consultas Hoje', $consultasHoje)
                ->description($consultasSucesso . ' sucessos, ' . $consultasErro . ' erros')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),

            Stat::make('Gasto Hoje', 'R$ ' . number_format($gastoHoje, 2, ',', '.'))
                ->description('Em consultas realizadas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),

            Stat::make('Empresas Ativas', $empresasAtivas)
                ->description('Empresas cadastradas')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('info'),

            Stat::make('Certificados', $certificadosAtivos)
                ->description($certificadosVencendo . ' vencendo em 30 dias')
                ->descriptionIcon('heroicon-m-key')
                ->color($certificadosVencendo > 0 ? 'danger' : 'success'),
        ];
    }
}
