<?php

namespace App\Console\Commands;

use App\Models\AutomacaoExecucao;
use Illuminate\Console\Command;

class LimparLogsAutomacaoCommand extends Command
{
    protected $signature = 'automacao:limpar-logs {--dias=30 : Manter logs dos últimos X dias}';
    protected $description = 'Remove logs de execução antigos';

    public function handle(): int
    {
        $dias = (int) $this->option('dias');
        $dataLimite = now()->subDays($dias);

        $count = AutomacaoExecucao::where('iniciada_em', '<', $dataLimite)->count();

        if ($count === 0) {
            $this->info('Nenhum log antigo encontrado para limpeza');
            return Command::SUCCESS;
        }

        if ($this->confirm("Remover {$count} logs de execução anteriores a {$dataLimite->format('d/m/Y')}?")) {
            $deleted = AutomacaoExecucao::where('iniciada_em', '<', $dataLimite)->delete();
            $this->info("✅ {$deleted} logs removidos com sucesso");
        } else {
            $this->info('❌ Limpeza cancelada');
        }

        return Command::SUCCESS;
    }
}
