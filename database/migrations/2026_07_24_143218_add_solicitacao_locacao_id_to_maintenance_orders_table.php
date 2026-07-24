<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // FK direta pra Solicitação de Locação -- mesma ideia que
            // EquipmentMovement.solicitacao_locacao_id já tinha, mas
            // MaintenanceOrder nunca teve. Sem isso, a "OS de Reserva"
            // criada em ReservasUrgentes::abrirOsReserva() ficaria
            // correlacionada só por Ativo+janela de tempo (best-effort,
            // igual ao resto do Histórico do Patrimônio) em vez de exata.
            $table->foreignUuid('solicitacao_locacao_id')->nullable()->after('maintenance_plan_id')
                ->constrained('solicitacoes_locacao')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitacao_locacao_id');
        });
    }
};
