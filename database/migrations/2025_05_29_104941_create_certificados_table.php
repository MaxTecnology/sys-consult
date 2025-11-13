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
        Schema::create('certificados', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->comment('Nome identificador do certificado');
            $table->text('pkcs12_cert_encrypted')->comment('Certificado .pfx criptografado');
            $table->text('pkcs12_pass_encrypted')->comment('Senha do certificado criptografada');
            $table->string('token_api')->comment('Token de acesso da InfoSimples');
            $table->string('chave_criptografia')->comment('Chave para descriptografar');
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->date('validade')->nullable()->comment('Data de validade do certificado');
            $table->text('observacoes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificados');
    }
};
