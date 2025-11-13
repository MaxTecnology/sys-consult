<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automacao_execucoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_automacao_id')->constrained('empresa_automacao')->onDelete('cascade');
            $table->foreignId('consulta_api_id')->nullable()->constrained('consultas_api')->onDelete('set null');

            // Informações da execução
            $table->enum('status', ['iniciada', 'sucesso', 'erro', 'timeout', 'cancelada'])->default('iniciada');
            $table->timestamp('iniciada_em');
            $table->timestamp('finalizada_em')->nullable();
            $table->integer('duracao_ms')->nullable()->comment('Duração em milissegundos');

            // Detalhes do resultado
            $table->text('mensagem_erro')->nullable();
            $table->json('detalhes_erro')->nullable()->comment('Stack trace, response da API, etc');
            $table->integer('tentativa_numero')->default(1)->comment('Número da tentativa (para retry)');

            // Informações do job
            $table->string('job_id')->nullable()->comment('ID do job na fila');
            $table->string('queue_name')->nullable()->comment('Nome da fila utilizada');

            // Métricas
            $table->decimal('custo_execucao', 8, 4)->nullable();
            $table->json('metricas_extras')->nullable()->comment('Métricas específicas da consulta');

            $table->timestamps();

            // Índices para performance
            $table->index(['status', 'iniciada_em']);
            $table->index(['empresa_automacao_id', 'iniciada_em']);
            $table->index('iniciada_em'); // Para relatórios por período
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automacao_execucoes');
    }
};
