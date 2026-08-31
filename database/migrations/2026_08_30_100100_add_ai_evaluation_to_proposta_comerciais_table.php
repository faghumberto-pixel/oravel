<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposta_comerciais', function (Blueprint $table) {
            $table->jsonb('ai_evaluation')->nullable()->after('total_value');
            $table->timestamp('ai_evaluated_at')->nullable()->after('ai_evaluation');
        });
    }

    public function down(): void
    {
        Schema::table('proposta_comerciais', function (Blueprint $table) {
            $table->dropColumn(['ai_evaluation', 'ai_evaluated_at']);
        });
    }
};
