<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contador incremental de ciclos de bateria (analogo a horimetro_atual)
     * -- ForkliftSpecification.battery_cycles ate' aqui era so' um valor
     * estatico/nominal do equipamento, sem historico de leituras. Alimentado
     * por BatteryCycleReading via BatteryCycleReadingObserver, mesmo padrao
     * de HorimeterReadingObserver.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedInteger('battery_cycles_atual')->default(0)->after('horimetro_atual');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('battery_cycles_atual');
        });
    }
};
