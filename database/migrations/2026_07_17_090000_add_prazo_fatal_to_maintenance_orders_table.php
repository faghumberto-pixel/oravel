<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prazo Fatal (locadoras de evento) -- disponivel em qualquer OS, nao
 * exclusivo de nicho (so' sugerido por padrao quando o cliente do Ativo e'
 * Client::NICHE_EVENTOS). Sem trava de bloqueio automatico: o checklist de
 * mobilizacao ja e' bloqueante (100% ou nao finaliza, ver
 * EquipmentMovementMobile::finalize()) -- este campo e' so' o alerta
 * visual/contagem regressiva no Kanban.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->boolean('is_prazo_fatal')->default(false);
            $table->timestamp('prazo_fatal_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn(['is_prazo_fatal', 'prazo_fatal_at']);
        });
    }
};
