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
            // Só cria o plan_id se ele ainda não existir no banco
            if (!Schema::hasColumn('tenants', 'plan_id')) {
                $table->foreignId('plan_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('plans')
                    ->nullOnDelete();
            }

            // Só cria o next_billing_date se ele ainda não existir no banco
            if (!Schema::hasColumn('tenants', 'next_billing_date')) {
                $table->date('next_billing_date')
                    ->nullable()
                    ->after('name'); // Ajustado para garantir que fique em local seguro
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
            
            if (Schema::hasColumn('tenants', 'next_billing_date')) {
                $table->dropColumn('next_billing_date');
            }
        });
    }
};