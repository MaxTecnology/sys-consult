<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dte_mailboxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            $table->string('canal')->default('sefaz-al');
            $table->string('tipo')->default('caixa-postal');
            $table->string('identificador_externo')->nullable();
            $table->timestamp('ultima_sincronizacao')->nullable();
            $table->timestamp('ultima_mensagem_recebida_em')->nullable();
            $table->unsignedInteger('total_mensagens')->default(0);
            $table->unsignedInteger('total_nao_lidas')->default(0);
            $table->json('configuracoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'canal', 'tipo'], 'dte_mailbox_empresa_tipo_unique');
            $table->index('ultima_sincronizacao');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_mailboxes');
    }
};
