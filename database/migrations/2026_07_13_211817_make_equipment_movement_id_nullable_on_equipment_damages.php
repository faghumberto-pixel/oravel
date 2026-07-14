<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ate aqui toda Avaria nascia obrigatoriamente presa a um EquipmentMovement
 * (so existia o fluxo mobile de mobilizacao/desmobilizacao). Agora a
 * propria O.S. tambem pode gerar Avaria (atendimento externo corretivo,
 * sem nenhuma movimentacao em andamento) -- nullable pra esse caso, sem
 * afetar avarias ja existentes (todas ja tem o campo preenchido).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_damages', function (Blueprint $table) {
            $table->dropForeign(['equipment_movement_id']);
        });

        Schema::table('equipment_damages', function (Blueprint $table) {
            $table->foreignUuid('equipment_movement_id')->nullable()->change();
        });

        Schema::table('equipment_damages', function (Blueprint $table) {
            $table->foreign('equipment_movement_id')->references('id')->on('equipment_movements')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_damages', function (Blueprint $table) {
            $table->dropForeign(['equipment_movement_id']);
        });

        Schema::table('equipment_damages', function (Blueprint $table) {
            $table->foreignUuid('equipment_movement_id')->nullable(false)->change();
        });

        Schema::table('equipment_damages', function (Blueprint $table) {
            $table->foreign('equipment_movement_id')->references('id')->on('equipment_movements')->cascadeOnDelete();
        });
    }
};
