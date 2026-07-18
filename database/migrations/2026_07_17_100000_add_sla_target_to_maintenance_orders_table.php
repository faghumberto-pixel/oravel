<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SLA de atendimento (locadoras industrial/hospitalar) -- so' relevante pra
 * OS tipo Emergencia. Minutos, nao horas, pra dar granularidade real de
 * "atendimento em X minutos" (diferente do padrao em horas ja usado em
 * EquipmentReplacement::SLA_HOURS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->integer('sla_target_minutes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn('sla_target_minutes');
        });
    }
};
