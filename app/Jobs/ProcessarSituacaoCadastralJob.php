<?php

namespace App\Jobs;

use App\Models\ConsultaApi;
use App\Services\InfoSimplesService;

class ProcessarSituacaoCadastralJob extends BaseConsultaJob
{
    protected function validarEspecifico(): void
    {
        // Validar se empresa tem CNPJ
        if (!$this->automacao->empresa->cnpj) {
            throw new \Exception('Empresa não possui CNPJ cadastrado');
        }

        // Para situação cadastral, pode não precisar de certificado
        // Validar conforme a API específica
    }

    protected function executarConsulta(): ConsultaApi
    {
        // TEMPORÁRIO: Criar consulta mockada para não quebrar o sistema
        // Remover quando a API real estiver implementada

        Log::warning('Usando consulta mockada para situação cadastral', [
            'empresa_id' => $this->automacao->empresa_id,
            'cnpj' => $this->automacao->empresa->cnpj
        ]);

        // Simular uma consulta bem-sucedida
        $consulta = new ConsultaApi();
        $consulta->empresa_id = $this->automacao->empresa_id;
        $consulta->tipo_consulta = 'situacao-cadastral';
        $consulta->sucesso = true;
        $consulta->code_message = 'Consulta mockada executada com sucesso';
        $consulta->preco = 0.50; // Preço simulado
        $consulta->resposta_data = [
            'status' => 'ativo',
            'situacao' => 'regular',
            'consultado_em' => now()->toISOString(),
            'mock' => true
        ];
        $consulta->save();

        return $consulta;

        /*
        // CÓDIGO REAL - Descomente quando a API estiver pronta:

        $service = new InfoSimplesService();

        $this->execucao->adicionarMetrica('inicio_consulta_api', now()->toISOString());

        try {
            $consulta = $service->consultarSituacaoCadastral(
                $this->automacao->empresa,
                $this->automacao->certificado
            );

            if ($consulta->sucesso && $consulta->resposta_data) {
                $this->adicionarMetricasSituacaoCadastral($consulta);
            }

            return $consulta;

        } catch (\Exception $e) {
            $this->execucao->adicionarMetrica('erro_consulta_api', $e->getMessage());
            throw $e;
        }
        */
    }

    private function adicionarMetricasSituacaoCadastral(ConsultaApi $consulta): void
    {
        // Para a versão mockada, adicionar algumas métricas básicas
        if (isset($consulta->resposta_data['status'])) {
            $this->execucao->adicionarMetrica('situacao_status', $consulta->resposta_data['status']);
        }

        if (isset($consulta->resposta_data['mock']) && $consulta->resposta_data['mock']) {
            $this->execucao->adicionarMetrica('consulta_mockada', true);
        }

        /*
        // MÉTRICAS REAIS - Implementar quando a API estiver pronta:

        $service = new InfoSimplesService();
        $estatisticas = $service->getEstatisticasSituacaoCadastral($consulta);

        $this->execucao->adicionarMetrica('situacao_ativa', $estatisticas['ativa']);
        $this->execucao->adicionarMetrica('situacao_pendencias', $estatisticas['pendencias']);

        if ($estatisticas['requer_atencao']) {
            $this->execucao->adicionarMetrica('requer_atencao', true);
            // event(new SituacaoIrregularDetectada($consulta));
        }
        */
    }
}
