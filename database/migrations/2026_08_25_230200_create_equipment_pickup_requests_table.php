<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitação de retirada de equipamento, feita pelo Client no Portal
 * quando o uso já acabou. Não reaproveita EquipmentMovement porque ele
 * não tem client_id (é vinculado a maintenance_order_id/
 * solicitacao_locacao_id, ambos nullable) -- rastrear "pedido de qual
 * cliente" via join indireto em Asset->Contract seria frágil se o
 * contrato mudar depois do pedido. Tabela minúscula, sem automação de
 * despacho -- aparece pro operador acionar manualmente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_pickup_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->string('status')->default('solicitado');
            $table->text('notes')->nullable();
            $table->timestamp('requested_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_pickup_requests');
    }
};
