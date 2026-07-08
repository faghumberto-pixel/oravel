<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ate aqui nao havia NENHUM campo dizendo "quem esta levando esse
 * equipamento agora" -- o vinculo com FreightRecord.fleet_vehicle_id e'
 * so custo, criado depois do fato, nao atribuicao operacional. Estes 2
 * campos viram o gancho que o EquipmentMovementObserver usa pra
 * automatizar FleetVehicle::status (disponivel <-> em_rota).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->foreignUuid('fleet_vehicle_id')->nullable()->after('asset_id')
                ->constrained()->nullOnDelete();
            $table->foreignUuid('fleet_driver_id')->nullable()->after('fleet_vehicle_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fleet_vehicle_id');
            $table->dropConstrainedForeignId('fleet_driver_id');
        });
    }
};
