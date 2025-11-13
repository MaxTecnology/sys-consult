<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automacao_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_consulta')->unique()->comment('Ex: caixa-postal, situacao-cadastral');
            $table->string('nome_exibicao')->comment('Nome para exibir na interface');
            $table->text('descricao')->nullable()->comment('Descrição do tipo de consulta');
            $table->boolean('habilitada')->default(false)->comment('Se automação está habilitada globalmente');
            $table->boolean('permite_automacao')->default(true)->comment('Se este tipo permite automação');

            // Configurações padrão
            $table->enum('frequencia_padrao', ['diaria', 'semanal', 'quinzenal', 'mensal'])->default('semanal');
            $table->tinyInteger('dia_semana_padrao')->default(2)->comment('1=Dom, 2=Seg, ..., 7=Sab');
            $table->time('horario_padrao')->default('02:00:00');
            $table->integer('intervalo_empresas_segundos')->default(30)->comment('Intervalo entre execuções de empresas');

            // Informações de custo e performance
            $table->decimal('custo_por_consulta', 8, 4)->nullable()->comment('Custo em reais por consulta');
            $table->integer('timeout_segundos')->default(300)->comment('Timeout da consulta em segundos');
            $table->integer('max_tentativas')->default(3)->comment('Máximo de tentativas em caso de erro');

            // Meta informações
            $table->json('configuracoes_extras')->nullable()->comment('Configurações específicas do tipo');
            $table->boolean('ativa')->default(true)->comment('Se o tipo está ativo no sistema');
            $table->timestamps();

            $table->index(['tipo_consulta', 'ativa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automacao_tipos');
    }
};
