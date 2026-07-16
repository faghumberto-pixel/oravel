<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estende MaterialRequest (Fase 2 do plano de Estoque/Compras/Troca) --
 * localizacao de destino, prioridade, data desejada, e motivo de recusa
 * estruturado (antes so' concatenado em notes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->foreignUuid('requested_for_location_id')->nullable()->after('maintenance_order_id')->constrained('internal_units')->nullOnDelete();
            $table->string('priority')->default('normal')->after('provider_name');
            $table->date('target_delivery_date')->nullable()->after('priority');
            $table->text('rejection_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('material_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_for_location_id');
            $table->dropColumn(['priority', 'target_delivery_date', 'rejection_reason']);
        });
    }
};
