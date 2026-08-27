<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bloqueio automático por PMP crítico vencido (CheckMaintenanceDueAlerts):
 * blocked_by_pmp_at marca QUANDO o Asset foi movido pra STATUS_MANUTENCAO
 * por esse motivo (não confundir com manutenção manual comum);
 * status_before_pmp_block guarda o status real de antes, pra reverter pro
 * valor certo (não sempre "disponível") quando o item crítico for
 * resolvido. Os dois nulos = nunca foi bloqueado por PMP, ou já foi
 * revertido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->timestamp('blocked_by_pmp_at')->nullable()->after('status');
            $table->string('status_before_pmp_block')->nullable()->after('blocked_by_pmp_at');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['blocked_by_pmp_at', 'status_before_pmp_block']);
        });
    }
};
