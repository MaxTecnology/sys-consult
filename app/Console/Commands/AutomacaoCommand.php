<?php

namespace App\Console\Commands;

use App\Jobs\ProcessarAutomacaoJob;
use App\Models\EmpresaAutomacao;
use Illuminate\Console\Command;

class AutomacaoCommand extends Command
{
    protected $signature = 'automacao:executar
                          {--tipo= : Tipo específico de consulta}
                          {--empresa= : ID específico da empresa}
                          {--dry-run : Apenas simular, não executar}
                          {--force : Não pedir confirmação antes de despachar}';

    protected $description = 'Executa automações de consultas programadas';

    public function handle(): int
    {
        $tipo = $this->option('tipo');
        $empresaId = $this->option('empresa');
        $dryRun = $this->option('dry-run');

        $this->info('Iniciando verificação de automações...');

        // Evitar executar com certificado inválido
        $certificadosInvalidos = \App\Models\Certificado::where(function ($q) {
            $q->where('status', 'inativo')
                ->orWhere(function ($q2) {
                    $q2->whereNotNull('validade')->where('validade', '<=', now());
                });
        })->count();

        if ($certificadosInvalidos > 0) {
            $this->warn("⚠️ Existem {$certificadosInvalidos} certificados inativos ou vencidos. Automações podem falhar.");
        }

        // Buscar automações prontas
        $query = EmpresaAutomacao::with(['empresa', 'automacaoTipo'])
            ->prontas()
            ->whereHas('automacaoTipo', function ($q) {
                $q->habilitadas()->ativas();
            });

        if ($tipo) {
            $query->porTipo($tipo);
            $this->info("Filtrando por tipo: {$tipo}");
        }

        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
            $this->info("Filtrando por empresa ID: {$empresaId}");
        }

        $automacoes = $query->orderBy('proxima_execucao')->get();

        if ($automacoes->isEmpty()) {
            $this->info('✅ Nenhuma automação pronta para execução');
            return Command::SUCCESS;
        }

        $this->info("📋 Encontradas {$automacoes->count()} automações prontas:");

        // Mostrar lista de automações
        $headers = ['Empresa', 'Tipo', 'Próxima Execução', 'Status'];
        $rows = $automacoes->map(function ($automacao) {
            return [
                $automacao->empresa->razao_social,
                $automacao->automacaoTipo->nome_exibicao,
                $automacao->proxima_execucao->format('d/m/Y H:i'),
                $automacao->status_formatado,
            ];
        });

        $this->table($headers, $rows);

        if ($dryRun) {
            $this->warn('🔍 Modo DRY-RUN ativado - nenhum job será despachado');
            return Command::SUCCESS;
        }

        // Confirmar execução, exceto se forçado (scheduler/flag)
        if (!$this->option('force')) {
            if (!$this->confirm('Deseja prosseguir com a execução?')) {
                $this->info('❌ Execução cancelada pelo usuário');
                return Command::SUCCESS;
            }
        }

        // Despachar job coordenador
        ProcessarAutomacaoJob::dispatch($tipo, $empresaId);

        $this->info('🚀 Job coordenador despachado com sucesso!');
        $this->info('📊 Use "sail artisan horizon:status" para monitorar');

        return Command::SUCCESS;
    }
}
