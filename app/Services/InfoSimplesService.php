<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Certificado;
use App\Models\ConsultaApi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Support\LogSanitizer;

class InfoSimplesService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.infosimples.base_url', 'https://api.infosimples.com/api/v2/consultas');
    }

    /**
     * Consulta caixa postal da SEFAZ/AL
     */
    public function consultarCaixaPostal(Empresa $empresa, Certificado $certificado): array
    {
        try {
            $url = $this->baseUrl . '/sefaz/al/dec/caixa-postal';

            $args = [
                'pkcs12_cert' => $certificado->pkcs12_cert_encrypted,
                'pkcs12_pass' => $certificado->pkcs12_pass_encrypted,
                'ie' => $empresa->inscricao_estadual,
                'token' => $certificado->token_api,
                'timeout' => 300
            ];

            $startTime = now();

            $response = Http::timeout(320) // timeout maior que o da API
            ->post($url, $args);

            $elapsedTime = now()->diffInMilliseconds($startTime);
            $responseData = $response->json();

            if ($response->successful() && isset($responseData['code'])) {
                return [
                    'sucesso' => $responseData['code'] == 200,
                    'response_code' => $responseData['code'],
                    'code_message' => $responseData['code_message'] ?? null,
                    'data' => $responseData['data'] ?? null,
                    'header' => $responseData['header'] ?? null,
                    'site_receipts' => $responseData['site_receipts'] ?? [],
                    'errors' => $responseData['errors'] ?? [],
                    'elapsed_time' => $elapsedTime,
                    'preco' => $responseData['header']['price'] ?? null,
                ];
            }

            return [
                'sucesso' => false,
                'response_code' => $response->status(),
                'code_message' => 'Erro na requisição HTTP',
                'data' => null,
                'header' => null,
                'site_receipts' => [],
                'errors' => ['Erro HTTP: ' . $response->status()],
                'elapsed_time' => $elapsedTime,
                'preco' => null,
            ];

        } catch (Exception $e) {
            Log::error('Erro ao consultar caixa postal: ' . $e->getMessage(), [
                'request_id' => request()->header('X-Request-Id'),
                'certificado_id' => $certificado->id ?? null,
                'empresa_id' => $empresa->id ?? null,
                'detalhes' => LogSanitizer::sanitize([
                    'status' => 'exception',
                ]),
            ]);

            return [
                'sucesso' => false,
                'response_code' => 500,
                'code_message' => 'Erro interno',
                'data' => null,
                'header' => null,
                'site_receipts' => [],
                'errors' => [$e->getMessage()],
                'elapsed_time' => 0,
                'preco' => null,
            ];
        }
    }

    /**
     * Salva o resultado da consulta
     */
    public function salvarConsulta(Empresa $empresa, Certificado $certificado, string $tipoConsulta, array $resultado, ?string $requestId = null): ConsultaApi
    {
        return ConsultaApi::create([
            'empresa_id' => $empresa->id,
            'certificado_id' => $certificado->id,
            'tipo_consulta' => $tipoConsulta,
            'parametro_consulta' => $empresa->inscricao_estadual,
            'resposta_data' => $resultado['data'],
            'resposta_header' => $resultado['header'],
            'site_receipts' => $resultado['site_receipts'],
            'response_code' => $resultado['response_code'],
            'code_message' => $resultado['code_message'],
            'request_id' => $requestId,
            'errors' => $resultado['errors'],
            'sucesso' => $resultado['sucesso'],
            'preco' => $resultado['preco'],
            'tempo_resposta_ms' => $resultado['elapsed_time'],
            'consultado_em' => now(),
        ]);
    }

    /**
     * Consulta completa (consulta + salva)
     */
    public function consultarEmpresaCaixaPostal(Empresa $empresa, Certificado $certificado, ?string $requestId = null): ConsultaApi
    {
        $requestId = $requestId ?: request()->header('X-Request-Id') ?: (string) \Illuminate\Support\Str::orderedUuid();

        $resultado = $this->consultarCaixaPostal($empresa, $certificado);
        $consulta = $this->salvarConsulta($empresa, $certificado, 'caixa-postal', $resultado, $requestId);

        // Atualiza timestamp da última consulta na empresa
        $empresa->update(['ultima_consulta_api' => now()]);

        return $consulta;
    }

    /**
     * Obter mensagens não lidas de uma consulta
     */
    public function getMensagensNaoLidas(ConsultaApi $consulta): array
    {
        if (!$consulta->sucesso || !$consulta->resposta_data) {
            return [];
        }

        $mensagensNaoLidas = [];

        foreach ($consulta->resposta_data as $item) {
            if (isset($item['mensagens'])) {
                foreach ($item['mensagens'] as $mensagem) {
                    if (!$mensagem['lida']) {
                        $mensagensNaoLidas[] = $mensagem;
                    }
                }
            }
        }

        return $mensagensNaoLidas;
    }

    /**
     * Obter todas as mensagens de uma consulta
     */
    public function getTodasMensagens(ConsultaApi $consulta): array
    {
        if (!$consulta->sucesso || !$consulta->resposta_data) {
            return [];
        }

        $todasMensagens = [];

        foreach ($consulta->resposta_data as $item) {
            if (isset($item['mensagens'])) {
                $todasMensagens = array_merge($todasMensagens, $item['mensagens']);
            }
        }

        return $todasMensagens;
    }

    /**
     * Obter estatísticas de uma consulta
     */
    public function getEstatisticasConsulta(ConsultaApi $consulta): array
    {
        if (!$consulta->sucesso || !$consulta->resposta_data) {
            return [
                'total_lidas' => 0,
                'total_nao_lidas' => 0,
                'total_mensagens' => 0,
            ];
        }

        $totalLidas = 0;
        $totalNaoLidas = 0;
        $totalMensagens = 0;

        foreach ($consulta->resposta_data as $item) {
            if (isset($item['lidas'])) {
                $totalLidas += $item['lidas'];
            }
            if (isset($item['nao_lidas'])) {
                $totalNaoLidas += $item['nao_lidas'];
            }
            if (isset($item['mensagens'])) {
                $totalMensagens += count($item['mensagens']);
            }
        }

        return [
            'total_lidas' => $totalLidas,
            'total_nao_lidas' => $totalNaoLidas,
            'total_mensagens' => $totalMensagens,
        ];
    }
}
