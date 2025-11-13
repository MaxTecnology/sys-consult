<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AutomacaoTiposSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'tipo_consulta' => 'caixa-postal',
                'nome_exibicao' => 'Caixa Postal SEFAZ/AL',
                'descricao' => 'Consulta mensagens da caixa postal eletrônica da SEFAZ de Alagoas',
                'habilitada' => true,
                'permite_automacao' => true,
                'frequencia_padrao' => 'semanal',
                'dia_semana_padrao' => 3, // Terça-feira
                'horario_padrao' => '02:00:00',
                'intervalo_empresas_segundos' => 30,
                'custo_por_consulta' => 0.20,
                'timeout_segundos' => 300,
                'max_tentativas' => 3,
                'configuracoes_extras' => json_encode([
                    'endpoint' => '/sefaz/al/dec/caixa-postal',
                    'requer_certificado' => true,
                    'requer_ie' => true
                ]),
                'ativa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tipo_consulta' => 'situacao-cadastral',
                'nome_exibicao' => 'Situação Cadastral',
                'descricao' => 'Consulta situação cadastral da empresa na Receita Federal',
                'habilitada' => false, // Desabilitada inicialmente
                'permite_automacao' => true,
                'frequencia_padrao' => 'mensal',
                'dia_semana_padrao' => 2,
                'horario_padrao' => '03:00:00',
                'intervalo_empresas_segundos' => 45,
                'custo_por_consulta' => 0.15,
                'timeout_segundos' => 180,
                'max_tentativas' => 2,
                'configuracoes_extras' => json_encode([
                    'endpoint' => '/receita-federal/situacao-cadastral',
                    'requer_certificado' => false,
                    'requer_cnpj' => true
                ]),
                'ativa' => false, // Será implementado futuramente
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tipo_consulta' => 'certidoes-negativas',
                'nome_exibicao' => 'Certidões Negativas',
                'descricao' => 'Emissão de certidões negativas federais, estaduais e municipais',
                'habilitada' => false,
                'permite_automacao' => false, // Apenas manual
                'frequencia_padrao' => 'mensal',
                'dia_semana_padrao' => 5,
                'horario_padrao' => '04:00:00',
                'intervalo_empresas_segundos' => 60,
                'custo_por_consulta' => 1.50,
                'timeout_segundos' => 600,
                'max_tentativas' => 1,
                'configuracoes_extras' => json_encode([
                    'tipos_certidao' => ['federal', 'estadual', 'municipal'],
                    'requer_certificado' => true,
                    'apenas_manual' => true
                ]),
                'ativa' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        DB::table('automacao_tipos')->insert($tipos);
    }
}
