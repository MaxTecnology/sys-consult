<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresa_automacao', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->string('tipo_consulta')->comment('Referência ao tipo de consulta');
            $table->foreignId('certificado_id')->constrained('certificados')->onDelete('cascade');

            // Status da automação
            $table->boolean('ativa')->default(false)->comment('Se automação está ativa para esta empresa/tipo');
            $table->enum('status', ['ativa', 'pausada', 'erro', 'desabilitada'])->default('desabilitada');

            // Configurações de frequência
            $table->enum('frequencia', ['diaria', 'semanal', 'quinzenal', 'mensal', 'personalizada'])->default('semanal');
            $table->integer('dias_personalizados')->nullable()->comment('Para frequência personalizada');
            $table->tinyInteger('dia_semana')->nullable()->comment('1=Dom, 2=Seg, ..., 7=Sab para semanal/quinzenal');
            $table->tinyInteger('dia_mes')->nullable()->comment('Dia do mês para frequência mensal (1-28)');
            $table->time('horario')->default('02:00:00');

            // Controle de execução
            $table->timestamp('proxima_execucao')->nullable();
            $table->timestamp('ultima_execucao')->nullable();
            $table->integer('tentativas_consecutivas_erro')->default(0);
            $table->timestamp('pausada_ate')->nullable()->comment('Data até quando está pausada por erro');

            // Configurações específicas
            $table->json('configuracoes_extras')->nullable()->comment('Configs específicas para esta empresa/tipo');
            $table->text('observacoes')->nullable();

            // Auditoria
            $table->foreignId('criado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('atualizado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Índices e constraints
            $table->unique(['empresa_id', 'tipo_consulta'], 'empresa_tipo_unique');
            $table->index(['ativa', 'proxima_execucao']);
            $table->index(['status', 'proxima_execucao']);
            $table->index('tipo_consulta');

            // Foreign key para automacao_tipos
            $table->foreign('tipo_consulta')->references('tipo_consulta')->on('automacao_tipos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_automacao');
    }
};
