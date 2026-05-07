<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Criar a tabela de Problemas Reportados
        if (!Schema::hasTable('reported_problems')) {
            Schema::create('reported_problems', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->string('description');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        // 2. Ajustar a tabela de Maintenance Orders
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Removendo a coluna antiga que era bigint e causava o erro
            if (Schema::hasColumn('maintenance_orders', 'technician_id')) {
                $table->dropColumn('technician_id');
            }
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Recriando como UUID
            $table->uuid('technician_id')->nullable();
            
            // Adicionando os novos campos necessários
            if (!Schema::hasColumn('maintenance_orders', 'reported_problem_id')) {
                $table->uuid('reported_problem_id')->nullable();
                $table->foreign('reported_problem_id')->references('id')->on('reported_problems');
            }

            if (!Schema::hasColumn('maintenance_orders', 'maintenance_type')) {
                $table->string('maintenance_type')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn(['technician_id', 'reported_problem_id', 'maintenance_type']);
        });
        Schema::dropIfExists('reported_problems');
    }
};