<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evento FORMAL de "chegada no patio" -- ate aqui, a desmobilizacao
 * confundia "checklist de coleta concluido no cliente" com "equipamento
 * fisicamente de volta no patio" (EquipmentMovementMobile::finalize() ja
 * liberava o ativo como disponivel na hora). Um por movimentacao
 * (unique), so faz sentido pra desmobilizacao -- exige confirmacao ativa
 * de um responsavel do patio, vira historico permanente por ativo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_patio_arrivals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('equipment_movement_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('arrived_at');
            $table->foreignUuid('confirmed_by_user_id')->constrained('users');
            $table->text('initial_condition_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_patio_arrivals');
    }
};
