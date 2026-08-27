<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itens de manutencao de cada familia (ver pmp_equipment_families) --
 * equivalente global ao que MaintenancePlan e' por tenant. Mesma dupla
 * interval_hours/interval_days que MaintenancePlan ja usa, pra o import
 * (MaintenancePlan::importFromFamilyTemplate()) copiar 1:1 sem
 * conversao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pmp_template_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pmp_equipment_family_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('periodicity_label');
            $table->unsignedInteger('interval_hours')->nullable();
            $table->unsignedInteger('interval_days')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pmp_template_items');
    }
};
