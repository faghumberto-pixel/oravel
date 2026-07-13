<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ate aqui nenhum campo representava "planejado para" -- started_at/
 * completed_at/vistoria_geral_captured_at so sao preenchidos quando o
 * operador ja esta executando a movimentacao no patio. Este campo permite
 * pre-agendar uma mobilizacao/desmobilizacao com antecedencia (ex: pela
 * Programacao) antes de o operador fisicamente comecar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->dateTime('scheduled_at')->nullable()->after('fleet_driver_id');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->dropColumn('scheduled_at');
        });
    }
};
