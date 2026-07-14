<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ate 2026-07-14 movimentacoes nascidas de um Despacho de Locacao
 * (solicitacao_locacao_id preenchido, sem maintenance_order_id) nunca
 * propagavam custo de frete pra lugar nenhum -- a cadeia de rollup so'
 * existia via MaintenanceOrder.logistics_cost. Este campo espelha o
 * mesmo padrao (ver EquipmentMovement::recalculateCustoTransporte()),
 * so' que pro lado da locacao.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes_locacao', function (Blueprint $table) {
            $table->decimal('logistics_cost', 10, 2)->default(0)->after('status_comercial');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes_locacao', function (Blueprint $table) {
            $table->dropColumn('logistics_cost');
        });
    }
};
