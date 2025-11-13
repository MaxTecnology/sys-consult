<?php

namespace App\Console\Commands;

use App\Models\EmpresaAutomacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReativarAutomacoesPausadasCommand extends Command
{
    protected $signature = 'automacao:reativar-pausadas
                            {--limite=100 : Número máximo de automações avaliadas por execução}
                            {--dry-run : Apenas simula, não altera registros}';

    protected $description = 'Reativa automações pausadas cujo período de pausa expirou e que cumprem os pré-requisitos.';

    public function handle(): int
    {
        $limite = (int) $this->option('limite');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('🔄 Buscando automações pausadas elegíveis...');

        $automacoes = EmpresaAutomacao::with(['empresa', 'certificado', 'automacaoTipo'])
            ->where('status', 'pausada')
            ->where(function ($query) {
                $query->whereNull('pausada_ate')
                    ->orWhere('pausada_ate', '<=', now());
            })
            ->orderBy('pausada_ate')
            ->limit($limite)
            ->get();

        if ($automacoes->isEmpty()) {
            $this->info('✅ Nenhuma automação pausada pronta para reativação.');
            return Command::SUCCESS;
        }

        $this->info("Encontradas {$automacoes->count()} automações para avaliar.");

        $reativadas = 0;
        $bloqueadas = 0;

        foreach ($automacoes as $automacao) {
            $motivoBloqueio = $this->motivoBloqueio($automacao);

            if ($motivoBloqueio) {
                $bloqueadas++;
                $this->warn("⛔ Automação #{$automacao->id} não pôde ser reativada: {$motivoBloqueio}");
                continue;
            }

            if ($dryRun) {
                $this->line("📝 [DRY-RUN] Automação #{$automacao->id} ({$automacao->empresa->razao_social}) seria reativada.");
                continue;
            }

            $automacao->reativar();
            $reativadas++;

            Log::info('Automação reativada automaticamente', [
                'automacao_id' => $automacao->id,
                'empresa_id' => $automacao->empresa_id,
                'tipo' => $automacao->tipo_consulta,
                'reativada_em' => now()->toIso8601String(),
            ]);

            $this->info("✅ Automação #{$automacao->id} reativada com sucesso.");
        }

        $this->line('---');
        $this->info("Resumo: {$reativadas} reativadas, {$bloqueadas} bloqueadas, {$automacoes->count()} avaliadas.");

        if ($dryRun && $reativadas === 0) {
            $this->comment('Modo DRY-RUN: nenhuma alteração foi persistida.');
        }

        return Command::SUCCESS;
    }

    private function motivoBloqueio(EmpresaAutomacao $automacao): ?string
    {
        if (!$automacao->ativa) {
            return 'flag de automação ativa está desabilitada';
        }

        if (!$automacao->empresa || $automacao->empresa->status !== 'ativo') {
            return 'empresa inativa ou inexistente';
        }

        if (!$automacao->certificado || $automacao->certificado->status !== 'ativo') {
            return 'certificado inativo';
        }

        if ($automacao->certificado->vencido) {
            return 'certificado vencido';
        }

        if (!$automacao->automacaoTipo || !$automacao->automacaoTipo->ativa || !$automacao->automacaoTipo->habilitada) {
            return 'tipo de automação desabilitado';
        }

        if ($automacao->pausada_ate && $automacao->pausada_ate->isFuture()) {
            return 'período de pausa ainda não expirou';
        }

        return null;
    }
}
