<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teste em Banco de Carga antes da liberacao (locadoras de evento/gerador)
 * -- registrado na propria movimentacao de mobilizacao, ultimo passo antes
 * de finalize() em EquipmentMovementMobile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->boolean('load_bank_tested')->default(false);
            $table->text('load_bank_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->dropColumn(['load_bank_tested', 'load_bank_notes']);
        });
    }
};
