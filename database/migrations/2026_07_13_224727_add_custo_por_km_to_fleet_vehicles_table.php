<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxa opcional de R$/km do veiculo -- usada pro EquipmentMovementMobile
 * calcular o custo do FreightRecord automaticamente ao finalizar uma
 * movimentacao. Se nula, o Frete nasce com valor=0 pro financeiro
 * completar depois (ver FreightRecordResource, sem mudanca nele).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->decimal('custo_por_km', 10, 2)->nullable()->after('km_atual');
        });
    }

    public function down(): void
    {
        Schema::table('fleet_vehicles', function (Blueprint $table) {
            $table->dropColumn('custo_por_km');
        });
    }
};
