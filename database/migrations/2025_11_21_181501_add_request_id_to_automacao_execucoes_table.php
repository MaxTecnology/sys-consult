<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automacao_execucoes', function (Blueprint $table) {
            $table->uuid('request_id')->nullable()->after('queue_name');
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::table('automacao_execucoes', function (Blueprint $table) {
            $table->dropIndex(['request_id']);
            $table->dropColumn('request_id');
        });
    }
};
