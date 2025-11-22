<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dte_messages', function (Blueprint $table) {
            $table->foreignId('visualizado_por')->nullable()->after('responsavel_id')->constrained('users')->onDelete('set null');
            $table->timestamp('visualizado_em')->nullable()->after('visualizado_por');
            $table->index('visualizado_em');
        });
    }

    public function down(): void
    {
        Schema::table('dte_messages', function (Blueprint $table) {
            $table->dropIndex(['visualizado_em']);
            $table->dropConstrainedForeignId('visualizado_por');
            $table->dropColumn('visualizado_em');
        });
    }
};
