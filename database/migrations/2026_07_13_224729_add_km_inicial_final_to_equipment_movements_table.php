<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot do odometro do FleetVehicle atribuido: km_inicial e' gravado
 * automaticamente quando o veiculo e' atribuido (EquipmentMovementMobile
 * ::updatedFleetVehicleId()); km_final e' obrigatorio pro motorista
 * informar antes de finalize() -- gate junto com o veiculo, nao so o
 * checklist 100%. km_percorrido (km_final - km_inicial) fica so como
 * accessor no model, nao duplicado em coluna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->decimal('km_inicial', 10, 2)->nullable()->after('fleet_driver_id');
            $table->decimal('km_final', 10, 2)->nullable()->after('km_inicial');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->dropColumn(['km_inicial', 'km_final']);
        });
    }
};
