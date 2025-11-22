<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dte_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dte_mailbox_id')->constrained('dte_mailboxes')->onDelete('cascade');
            $table->foreignId('consulta_api_id')->nullable()->constrained('consultas_api')->onDelete('set null');
            $table->foreignId('automacao_execucao_id')->nullable()->constrained('automacao_execucoes')->onDelete('set null');
            $table->string('message_uid')->nullable();
            $table->string('hash_unico')->unique();
            $table->string('remetente')->nullable();
            $table->string('assunto')->nullable();
            $table->string('protocolo')->nullable();
            $table->string('categoria')->nullable();
            $table->string('numero_documento')->nullable();
            $table->boolean('lida_sefaz')->default(false);
            $table->timestamp('data_envio')->nullable();
            $table->timestamp('data_leitura_sefaz')->nullable();
            $table->timestamp('disponivel_ate')->nullable();
            $table->enum('status_interno', ['novo', 'em_andamento', 'concluido', 'ignorado'])->default('novo');
            $table->foreignId('responsavel_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('primeira_visualizacao_em')->nullable();
            $table->timestamp('ultima_interacao_em')->nullable();
            $table->text('resumo')->nullable();
            $table->longText('conteudo_texto')->nullable();
            $table->longText('conteudo_html')->nullable();
            $table->json('anexos')->nullable();
            $table->json('metadados')->nullable();
            $table->boolean('requere_atencao')->default(false);
            $table->timestamps();

            $table->index(['dte_mailbox_id', 'status_interno']);
            $table->index(['lida_sefaz', 'requere_atencao']);
            $table->index('data_envio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_messages');
    }
};
