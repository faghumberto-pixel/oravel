<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Terceira dimensao de vencimento, paralela a interval_hours/
     * interval_days (mesmo criterio OR ja usado em
     * MaintenancePlan::dueStatusForAsset()) -- relevante pra PTA/
     * empilhadeira eletrica, onde o fabricante recomenda manutencao a
     * cada N ciclos de carga da bateria, nao so' por hora rodada ou data.
     */
    public function up(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->unsignedInteger('interval_battery_cycles')->nullable()->after('interval_days');
            $table->unsignedInteger('last_service_battery_cycles')->nullable()->after('last_service_hours');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->dropColumn(['interval_battery_cycles', 'last_service_battery_cycles']);
        });
    }
};
