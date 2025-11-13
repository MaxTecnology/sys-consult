<?php

namespace App\Jobs;

use App\Models\ConsultaApi;
use App\Services\InfoSimplesService;

class ProcessarCaixaPostalJob extends BaseConsultaJob
{
    protected function validarEspecifico(): void
    {
        // Validar se empresa tem inscrição estadual
        if (!$this->automacao->empresa->inscricao_estadual) {
            throw new \Exception('Empresa não possui inscrição estadual cadastrada');
        }

        // Validar se certificado tem os dados necessários
        if (!$this->automacao->certificado->pkcs12_cert_encrypted ||
            !$this->automacao->certificado->pkcs12_pass_encrypted ||
            !$this->automacao->certificado->token_api) {
            throw new \Exception('Certificado não possui dados completos para consulta');
        }
    }

    protected function executarConsulta(): ConsultaApi
    {
        $service = new InfoSimplesService();

        // Adicionar métricas de início
        $this->execucao->adicionarMetrica('inicio_consulta_api', now()->toISOString());

        try {
            $consulta = $service->consultarEmpresaCaixaPostal(
                $this->automacao->empresa,
                $this->automacao->certificado
            );

            // Adicionar métricas específicas da caixa postal
            if ($consulta->sucesso && $consulta->resposta_data) {
                $this->adicionarMetricasCaixaPostal($consulta);
            }

            return $consulta;

        } catch (\Exception $e) {
            $this->execucao->adicionarMetrica('erro_consulta_api', $e->getMessage());
            throw $e;
        }
    }

    private function adicionarMetricasCaixaPostal(ConsultaApi $consulta): void
    {
        $service = new InfoSimplesService();
        $estatisticas = $service->getEstatisticasConsulta($consulta);

        $this->execucao->adicionarMetrica('mensagens_total', $estatisticas['total_mensagens']);
        $this->execucao->adicionarMetrica('mensagens_lidas', $estatisticas['total_lidas']);
        $this->execucao->adicionarMetrica('mensagens_nao_lidas', $estatisticas['total_nao_lidas']);

        // Se há mensagens não lidas, pode ser importante notificar
        if ($estatisticas['total_nao_lidas'] > 0) {
            $this->execucao->adicionarMetrica('requer_atencao', true);

            // Aqui poderia disparar uma notificação
            // event(new MensagensNaoLidasDetectadas($consulta, $estatisticas['total_nao_lidas']));
        }
    }
}
