<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_vehicles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('placa')->unique();
            $table->string('modelo');
            $table->string('tipo');
            $table->decimal('capacidade_carga', 10, 2)->nullable();
            $table->string('status')->default('disponivel');
            $table->decimal('km_atual', 12, 2)->default(0);
            $table->string('tag_sem_parar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_vehicles');
    }
};
