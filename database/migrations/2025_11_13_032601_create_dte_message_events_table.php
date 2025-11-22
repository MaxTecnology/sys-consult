<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dte_message_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dte_message_id')->constrained('dte_messages')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('tipo_evento');
            $table->text('descricao')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('registrado_em')->useCurrent();
            $table->timestamps();

            $table->index(['dte_message_id', 'tipo_evento']);
            $table->index('registrado_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_message_events');
    }
};
