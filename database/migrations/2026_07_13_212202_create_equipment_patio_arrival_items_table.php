<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laudo de Recebimento -- mesma estrutura de equipment_movement_items,
 * so' que pendurada em EquipmentPatioArrival em vez de EquipmentMovement.
 * completed_at em equipment_patio_arrivals marca quando o CHECKLIST (nao
 * so' o registro) foi finalizado -- o registro em si nasce no inicio do
 * checklist (rascunho), completed_at so' e' preenchido quando os itens
 * chegam a 100% (EquipmentPatioArrivalMobile::finalize()), gate real
 * pra liberar o ativo como disponivel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_patio_arrival_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('equipment_patio_arrival_id')->constrained()->cascadeOnDelete();
            $table->string('section');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('requires_photo')->default(false);
            $table->boolean('is_checked')->default(false);
            $table->string('value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('equipment_patio_arrivals', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('initial_condition_notes');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_patio_arrivals', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });

        Schema::dropIfExists('equipment_patio_arrival_items');
    }
};
