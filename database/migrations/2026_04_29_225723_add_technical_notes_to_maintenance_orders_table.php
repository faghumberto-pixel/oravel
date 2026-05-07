<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adiciona a coluna de notas técnicas para permitir que o técnico
     * salve o relatório de manutenção no banco de dados.
     */
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Adicionamos como text para permitir descrições detalhadas
            // nullable() é essencial para não quebrar registros antigos
            $table->text('technical_notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     * Remove a coluna caso seja necessário reverter a alteração.
     */
    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn('technical_notes');
        });
    }
};