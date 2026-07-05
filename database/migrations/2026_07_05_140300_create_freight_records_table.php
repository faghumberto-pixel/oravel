<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('freight_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('equipment_movement_id')->constrained()->cascadeOnDelete();
            $table->string('tipo');
            $table->foreignUuid('fleet_vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('freight_carrier_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('valor', 10, 2)->default(0);
            $table->string('origem')->nullable();
            $table->string('destino')->nullable();
            $table->decimal('km_percorrido', 10, 2)->nullable();
            $table->date('data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('freight_records');
    }
};
