<?php

namespace App\Jobs;

use App\Models\AutomacaoTipo;
use App\Models\EmpresaAutomacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessarAutomacaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Não fazer retry do coordenador
    public int $timeout = 60; // 1 minuto para processar

    public function __construct(
        public ?string $tipoConsulta = null,
        public ?int $empresaId = null
    ) {
        // Usar fila default
        $this->onQueue('default');
    }

    public function handle(): void
    {
        Log::info('Iniciando processamento de automação', [
            'tipo_consulta' => $this->tipoConsulta,
            'empresa_id' => $this->empresaId
        ]);

        try {
            $automacoesProntas = $this->buscarAutomacoesProntas();

            if ($automacoesProntas->isEmpty()) {
                Log::info('Nenhuma automação pronta para execução');
                return;
            }

            Log::info("Encontradas {$automacoesProntas->count()} automações para processar");

            $this->despacharJobs($automacoesProntas);

        } catch (\Exception $e) {
            Log::error('Erro no coordenador de automação', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    private function buscarAutomacoesProntas()
    {
        $query = EmpresaAutomacao::query()
            ->with(['empresa', 'certificado', 'automacaoTipo'])
            ->prontas() // Scope: ativas + proxima_execucao <= now
            ->whereHas('automacaoTipo', function ($q) {
                $q->habilitadas()->ativas();
            });

        // Filtros opcionais
        if ($this->tipoConsulta) {
            $query->porTipo($this->tipoConsulta);
        }

        if ($this->empresaId) {
            $query->where('empresa_id', $this->empresaId);
        }

        return $query->orderBy('proxima_execucao')->get();
    }

    private function despacharJobs($automacoes): void
    {
        $delay = 0; // Delay inicial em segundos
        $jobsDespachados = 0;

        foreach ($automacoes as $automacao) {
            try {
                $intervalo = $automacao->automacaoTipo->intervalo_empresas_segundos ?? 30;

                // Criar job específico
                $job = $this->criarJobEspecifico($automacao);

                if ($job) {
                    // Aplicar delay se necessário
                    if ($delay > 0) {
                        dispatch($job->delay(now()->addSeconds($delay)));
                    } else {
                        dispatch($job);
                    }

                    Log::info('Job despachado', [
                        'tipo' => $automacao->tipo_consulta,
                        'empresa' => $automacao->empresa->razao_social,
                        'delay' => $delay
                    ]);

                    $jobsDespachados++;
                    $delay += $intervalo;
                } else {
                    Log::warning('Tipo de consulta não implementado', [
                        'tipo' => $automacao->tipo_consulta,
                        'automacao_id' => $automacao->id
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Erro ao despachar job individual', [
                    'automacao_id' => $automacao->id,
                    'tipo' => $automacao->tipo_consulta,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info("Processo concluído", [
            'total_automacoes' => $automacoes->count(),
            'jobs_despachados' => $jobsDespachados,
            'delay_total_segundos' => $delay
        ]);
    }

    private function criarJobEspecifico(EmpresaAutomacao $automacao)
    {
        return match($automacao->tipo_consulta) {
            'caixa-postal' => new ProcessarCaixaPostalJob($automacao->id),
            'situacao-cadastral' => new ProcessarSituacaoCadastralJob($automacao->id),
            default => null
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha no coordenador de automação', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'tipo_consulta' => $this->tipoConsulta,
            'empresa_id' => $this->empresaId
        ]);
    }
}
