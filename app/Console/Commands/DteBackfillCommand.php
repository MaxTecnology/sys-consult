<?php

namespace App\Console\Commands;

use App\Models\ConsultaApi;
use App\Services\DteMessageSyncService;
use Illuminate\Console\Command;

class DteBackfillCommand extends Command
{
    protected $signature = 'dte:backfill-messages
                            {--consulta_id= : ID específico de consulta para reprocessar}
                            {--empresa_id= : Limitar a uma empresa}
                            {--limit=500 : Número máximo de consultas processadas nesta execução}';

    protected $description = 'Converte consultas API históricas em registros estruturados de mensagens DTE.';

    public function handle(): int
    {
        $service = app(DteMessageSyncService::class);
        $query = ConsultaApi::with(['empresa'])
            ->whereNotNull('resposta_data')
            ->orderBy('consultado_em');

        if ($id = $this->option('consulta_id')) {
            $query->where('id', $id);
        }

        if ($empresaId = $this->option('empresa_id')) {
            $query->where('empresa_id', $empresaId);
        }

        $limit = (int) $this->option('limit');
        $consultas = $query->limit($limit)->get();

        if ($consultas->isEmpty()) {
            $this->info('Nenhuma consulta disponível para backfill.');
            return Command::SUCCESS;
        }

        $this->info('Processando ' . $consultas->count() . ' consultas...');

        $resumo = [
            'consultas' => 0,
            'mensagens_importadas' => 0,
            'mensagens_atualizadas' => 0,
        ];

        foreach ($consultas as $consulta) {
            $resumo['consultas']++;

            try {
                $resultado = $service->syncFromConsulta($consulta);
                $resumo['mensagens_importadas'] += $resultado['importadas'] ?? 0;
                $resumo['mensagens_atualizadas'] += $resultado['atualizadas'] ?? 0;

                $this->line(sprintf(
                    '#%d (%s) -> +%d importadas / +%d atualizadas',
                    $consulta->id,
                    $consulta->empresa?->razao_social ?? 'N/A',
                    $resultado['importadas'] ?? 0,
                    $resultado['atualizadas'] ?? 0
                ));
            } catch (\Throwable $e) {
                $this->error(sprintf('#%d falhou: %s', $consulta->id, $e->getMessage()));
            }
        }

        $this->info('---');
        $this->info('Consultas processadas: ' . $resumo['consultas']);
        $this->info('Mensagens importadas: ' . $resumo['mensagens_importadas']);
        $this->info('Mensagens atualizadas: ' . $resumo['mensagens_atualizadas']);

        return Command::SUCCESS;
    }
}
