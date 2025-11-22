<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa_automacao', function (Blueprint $table) {
            $table->boolean('ativo')->default(true)->after('observacoes');
            $table->index('ativo');
        });

        Schema::table('certificados', function (Blueprint $table) {
            $table->boolean('ativo')->default(true)->after('status');
            $table->index('ativo');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_automacao', function (Blueprint $table) {
            $table->dropIndex(['ativo']);
            $table->dropColumn('ativo');
        });

        Schema::table('certificados', function (Blueprint $table) {
            $table->dropIndex(['ativo']);
            $table->dropColumn('ativo');
        });
    }
};
