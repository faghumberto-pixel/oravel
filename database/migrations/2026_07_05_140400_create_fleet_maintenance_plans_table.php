<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_maintenance_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('fleet_vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('tipo_servico');
            $table->unsignedInteger('intervalo_km')->nullable();
            $table->unsignedInteger('intervalo_dias')->nullable();
            $table->decimal('ultima_execucao_km', 12, 2)->nullable();
            $table->date('ultima_execucao_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_maintenance_plans');
    }
};
