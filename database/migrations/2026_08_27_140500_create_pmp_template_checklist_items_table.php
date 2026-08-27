<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklist tecnico (secoes + itens C/NC/NA) por familia de equipamento --
 * catalogo global (sem tenant_id, mesmo padrao de pmp_equipment_families/
 * pmp_template_items). No import (MaintenancePlan::importFromFamilyTemplate())
 * vira MaintenanceOrderChecklist is_template=true no ChecklistGroup do
 * tenant -- de onde MaintenanceOrderChecklistSnapshotObserver ja copia
 * pra qualquer OS nova daquele grupo, sem precisar mexer no Observer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pmp_template_checklist_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pmp_equipment_family_id')->constrained()->cascadeOnDelete();
            $table->string('section');
            $table->string('item_name');
            $table->text('instructions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pmp_template_checklist_items');
    }
};
