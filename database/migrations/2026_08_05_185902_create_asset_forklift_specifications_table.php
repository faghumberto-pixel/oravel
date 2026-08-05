<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_forklift_specifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('load_capacity_kg', 10, 2)->nullable();
            $table->decimal('lift_height_m', 6, 2)->nullable();
            $table->string('energy_type')->nullable();
            $table->string('mast_type')->nullable();
            $table->string('tire_type')->nullable();
            $table->unsignedInteger('battery_cycles')->nullable();
            $table->string('battery_voltage')->nullable();
            $table->decimal('battery_amperage_ah', 8, 2)->nullable();
            $table->string('battery_serial_number')->nullable();
            $table->string('charger_model')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_forklift_specifications');
    }
};
