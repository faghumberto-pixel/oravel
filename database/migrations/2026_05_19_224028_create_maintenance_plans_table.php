<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            
            $table->string('name'); // Ex: "Preventiva 250h"
            $table->integer('interval_hours'); // Ex: 250
            $table->integer('last_service_hours')->default(0); // Último horímetro em que foi feita
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_plans');
    }
};