<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Adiciona horas para o relatório da Superinfra
            $table->decimal('hours_spent', 8, 2)->default(0.00)->after('status');
            
            // Garante que o status exista para o Kanban (se já existir, remova esta linha)
            // Caso sua tabela já tenha o status, pule esta linha ou verifique se o tipo é string
            if (!Schema::hasColumn('maintenance_orders', 'status')) {
                $table->string('status')->default('aberto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn('hours_spent');
        });
    }
};