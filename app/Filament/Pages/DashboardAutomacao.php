<?php

// app/Filament/Pages/DashboardAutomacao.php
namespace App\Filament\Pages;

use App\Models\EmpresaAutomacao;
use App\Models\AutomacaoExecucao;
use App\Models\ConsultaApi;
use App\Jobs\ProcessarAutomacaoJob;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DashboardAutomacao extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Dashboard Automação';
    protected static ?string $title = 'Monitoramento de Automação';
    protected static string $view = 'filament.pages.dashboard-automacao';
    protected static ?int $navigationSort = 1;

    public function getHeaderActions(): array
    {
        return [
            Action::make('executar_automacoes')
                ->label('Executar Automações Prontas')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->action(function () {
                    $prontas = EmpresaAutomacao::prontas()->count();

                    if ($prontas === 0) {
                        Notification::make()
                            ->title('Nenhuma automação pronta')
                            ->warning()
                            ->send();
                        return;
                    }

                    ProcessarAutomacaoJob::dispatch();

                    Notification::make()
                        ->title('Execução iniciada!')
                        ->body("{$prontas} automações foram adicionadas à fila")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),

            Action::make('ver_horizon')
                ->label('Ver Horizon')
                ->icon('heroicon-o-queue-list')
                ->color('info')
                ->url('/horizon', shouldOpenInNewTab: true),
        ];
    }

    public function getViewData(): array
    {
        return [
            'stats' => $this->getStats(),
            'proximasExecucoes' => $this->getProximasExecucoes(),
            'ultimasExecucoes' => $this->getUltimasExecucoes(),
            'automacoesComErro' => $this->getAutomacoesComErro(),
        ];
    }

    private function getStats(): array
    {
        return [
            'automacoes_ativas' => EmpresaAutomacao::ativas()->count(),
            'automacoes_prontas' => EmpresaAutomacao::prontas()->count(),
            'automacoes_erro' => EmpresaAutomacao::comErro()->count(),
            'execucoes_hoje' => AutomacaoExecucao::hoje()->count(),
            'sucesso_hoje' => AutomacaoExecucao::hoje()->sucesso()->count(),
            'erro_hoje' => AutomacaoExecucao::hoje()->erro()->count(),
            'custo_hoje' => ConsultaApi::whereDate('consultado_em', today())->sum('preco') ?? 0,
        ];
    }

    private function getProximasExecucoes()
    {
        return EmpresaAutomacao::ativas()
            ->with(['empresa', 'automacaoTipo'])
            ->whereNotNull('proxima_execucao')
            ->orderBy('proxima_execucao')
            ->limit(10)
            ->get();
    }

    private function getUltimasExecucoes()
    {
        return AutomacaoExecucao::with(['empresaAutomacao.empresa', 'empresaAutomacao.automacaoTipo'])
            ->orderBy('iniciada_em', 'desc')
            ->limit(10)
            ->get();
    }

    private function getAutomacoesComErro()
    {
        return EmpresaAutomacao::comErro()
            ->with(['empresa', 'automacaoTipo'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();
    }
}
