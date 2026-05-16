<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Se a coluna ainda não existir, cria como UUID
            if (!Schema::hasColumn('tenants', 'plan_id')) {
                $table->foreignUuid('plan_id')->nullable()->constrained('plans')->onDelete('set null');
            }
            
            // Adicione aqui outros campos que a sua migration tentava criar (ex: trial_ends_at)
            if (!Schema::hasColumn('tenants', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'trial_ends_at']);
        });
    }
};