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
        Schema::table('tenants', function (Blueprint $table) {
            // Adiciona o vínculo com o plano usando UUID
            if (!Schema::hasColumn('tenants', 'plan_id')) {
                $table->foreignUuid('plan_id')
                    ->nullable() // Permite que o cliente comece sem plano ou em período de teste
                    ->constrained('plans') // Assume que sua tabela de planos se chama 'plans'
                    ->nullOnDelete(); // Se o plano for deletado, o cliente não é apagado
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'plan_id')) {
                $table->dropConstrainedForeignId('plan_id');
            }
        });
    }
};