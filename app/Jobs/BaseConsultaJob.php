<?php

namespace App\Jobs;

use App\Models\EmpresaAutomacao;
use App\Models\AutomacaoExecucao;
use App\Models\ConsultaApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Support\LogSanitizer;

abstract class BaseConsultaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min
    public int $timeout = 320; // Maior que timeout da API

    protected EmpresaAutomacao $automacao;
    protected AutomacaoExecucao $execucao;
    protected string $requestId;

    public function __construct(public int $automacaoId)
    {
        // Usar fila default para simplicidade
        $this->onQueue('default');
    }

    public function handle(): void
    {
        try {
            $this->requestId = (string) Str::orderedUuid();
            Log::withContext(['request_id' => $this->requestId]);

            $this->carregarAutomacao();
            $this->validarPreRequisitos();
            $this->aplicarRateLimit();
            $this->criarLogExecucao();

            Log::info('Iniciando consulta automática', [
                'tipo' => $this->automacao->tipo_consulta,
                'empresa' => $this->automacao->empresa->razao_social,
                'tentativa' => $this->attempts()
            ]);

            $resultado = $this->executarConsulta();
            $this->processarResultado($resultado);

        } catch (\Exception $e) {
            $this->tratarErro($e);
            throw $e;
        }
    }

    protected function carregarAutomacao(): void
    {
        $this->automacao = EmpresaAutomacao::with([
            'empresa',
            'certificado',
            'automacaoTipo'
        ])->findOrFail($this->automacaoId);
    }

    protected function validarPreRequisitos(): void
    {
        // Verificar se automação ainda está ativa
        if (!$this->automacao->ativa || $this->automacao->status !== 'ativa') {
            throw new \Exception('Automação não está ativa');
        }

        // Verificar se está pausada
        if ($this->automacao->esta_pausada) {
            throw new \Exception('Automação está pausada até ' . $this->automacao->pausada_ate);
        }

        // Verificar empresa
        if ($this->automacao->empresa->status !== 'ativo') {
            throw new \Exception('Empresa não está ativa');
        }

        // Verificar certificado
        if ($this->automacao->certificado->status !== 'ativo') {
            throw new \Exception('Certificado não está ativo');
        }

        if ($this->automacao->certificado->vencido) {
            throw new \Exception('Certificado está vencido');
        }

        // Validações específicas do tipo
        $this->validarEspecifico();
    }

    protected function aplicarRateLimit(): void
    {
        $key = "empresa-{$this->automacao->empresa_id}";

        if (RateLimiter::tooManyAttempts($key, 2)) {
            $seconds = RateLimiter::availableIn($key);
            throw new \Exception("Rate limit excedido. Tente novamente em {$seconds} segundos");
        }

        RateLimiter::hit($key, 60); // 2 por minuto
    }

    protected function criarLogExecucao(): void
    {
        $this->execucao = AutomacaoExecucao::create([
            'empresa_automacao_id' => $this->automacao->id,
            'status' => 'iniciada',
            'iniciada_em' => now(),
            'tentativa_numero' => $this->attempts(),
            'job_id' => $this->job->getJobId(),
            'queue_name' => 'default',
            'request_id' => $this->requestId,
        ]);
    }

    protected function processarResultado(ConsultaApi $consulta): void
    {
        if ($consulta->sucesso) {
            // Marcar execução como sucesso
            $this->execucao->marcarComoSucesso($consulta->id, $consulta->preco);

            // Atualizar automação
            $this->automacao->marcarComoExecutada();

            Log::info('Consulta automática executada com sucesso', [
                'consulta_id' => $consulta->id,
                'empresa' => $this->automacao->empresa->razao_social,
                'custo' => $consulta->preco
            ]);

        } else {
            throw new \Exception("Consulta falhou: {$consulta->code_message}");
        }
    }

    protected function tratarErro(\Exception $e): void
    {
        Log::error('Erro na consulta automática', [
            'error' => $e->getMessage(),
            'empresa' => $this->automacao->empresa->razao_social ?? 'N/A',
            'tipo' => $this->automacao->tipo_consulta ?? 'N/A',
            'tentativa' => $this->attempts(),
            'request_id' => $this->requestId ?? null,
            'detalhes' => LogSanitizer::sanitize([
                'automacao_id' => $this->automacaoId,
                'job_id' => $this->job?->getJobId(),
            ]),
        ]);

        // Marcar execução como erro
        if (isset($this->execucao)) {
            $this->execucao->marcarComoErro($e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'tentativa' => $this->attempts()
            ]);
        }

        // Se é a última tentativa, marcar automação com erro
        if ($this->attempts() >= $this->tries) {
            $this->automacao->incrementarErro();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Job de consulta falhou definitivamente', [
            'error' => $exception->getMessage(),
            'automacao_id' => $this->automacaoId,
            'tentativas' => $this->attempts()
        ]);

        // Marcar como erro final se execução existe
        if (isset($this->execucao)) {
            $this->execucao->marcarComoErro(
                'Falha definitiva após ' . $this->tries . ' tentativas',
                ['error' => $exception->getMessage()]
            );
        }
    }

    // Métodos abstratos que devem ser implementados pelas classes filhas
    abstract protected function validarEspecifico(): void;
    abstract protected function executarConsulta(): ConsultaApi;
}
