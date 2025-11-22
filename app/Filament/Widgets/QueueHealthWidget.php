<?php

namespace App\Filament\Widgets;

use App\Models\AutomacaoExecucao;
use App\Models\EmpresaAutomacao;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QueueHealthWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $ultimoIntervalo = now()->subHour();

        $execucoesIniciadas = AutomacaoExecucao::where('iniciada_em', '>=', $ultimoIntervalo)->count();
        $execucoesErro = AutomacaoExecucao::where('created_at', '>=', $ultimoIntervalo)
            ->whereIn('status', ['erro', 'timeout'])
            ->count();
        $execucoesSucesso = AutomacaoExecucao::where('created_at', '>=', $ultimoIntervalo)
            ->where('status', 'sucesso')
            ->count();

        $automacoesPausadas = EmpresaAutomacao::where('status', 'pausada')->count();
        $automacoesErro = EmpresaAutomacao::where('status', 'erro')->count();

        return [
            Stat::make('Execuções última hora', $execucoesIniciadas)
                ->description($execucoesSucesso . ' sucesso, ' . $execucoesErro . ' erro')
                ->descriptionIcon('heroicon-m-clock')
                ->color($execucoesErro > 0 ? 'warning' : 'success'),

            Stat::make('Automações pausadas', $automacoesPausadas)
                ->description('Verifique certificados/erros')
                ->descriptionIcon('heroicon-m-pause')
                ->color($automacoesPausadas > 0 ? 'danger' : 'success'),

            Stat::make('Automações com erro', $automacoesErro)
                ->description('Jobs bloqueados')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($automacoesErro > 0 ? 'danger' : 'success'),
        ];
    }
}
