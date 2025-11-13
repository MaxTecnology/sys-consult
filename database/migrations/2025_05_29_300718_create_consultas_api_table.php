<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('consultas_api', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->foreignId('certificado_id')->constrained('certificados')->onDelete('cascade');
            $table->string('tipo_consulta'); // 'caixa-postal', 'situacao-cadastral', etc
            $table->string('parametro_consulta'); // IE da empresa consultada
            $table->json('resposta_data')->nullable(); // response_json['data']
            $table->json('resposta_header')->nullable(); // response_json['header']
            $table->json('site_receipts')->nullable(); // URLs dos comprovantes
            $table->integer('response_code')->nullable(); // código de retorno da API
            $table->string('code_message')->nullable(); // mensagem do código
            $table->json('errors')->nullable(); // erros da API
            $table->boolean('sucesso')->default(true);
            $table->decimal('preco', 8, 2)->nullable(); // preço da consulta
            $table->integer('tempo_resposta_ms')->nullable(); // tempo de resposta em ms
            $table->timestamp('consultado_em');
            $table->timestamps();

            $table->index(['empresa_id', 'tipo_consulta']);
            $table->index(['certificado_id', 'consultado_em']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consultas_api');
    }
};
