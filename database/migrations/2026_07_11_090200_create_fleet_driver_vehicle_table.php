<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: quais veiculos um motorista esta habilitado a dirigir (ex:
 * motorista de carreta vs motorista de caminhao menor). Mesmo padrao de
 * material_checklist_group (id incremental, sem tenant_id proprio -- o
 * isolamento vem dos dois lados da relacao ja serem do mesmo tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_driver_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('fleet_driver_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('fleet_vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['fleet_driver_id', 'fleet_vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_driver_vehicle');
    }
};
