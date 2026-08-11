<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Causa/categoria de falha estruturada (hidraulico/eletrico/motor/
     * pneu_esteira/estrutural/outro) em toda OS Corretiva -- mesmo
     * vocabulario de EquipmentDamage::DAMAGE_TYPE_*, que so' e
     * preenchido quando o fluxo de avaria e usado (fatia parcial das
     * corretivas). Nullable de proposito: OS antigas ficam sem essa
     * informacao, passa a ser capturado a partir de agora.
     */
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('maintenance_orders', 'failure_category')) {
                $table->string('failure_category')->nullable()->after('maintenance_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_orders', 'failure_category')) {
                $table->dropColumn('failure_category');
            }
        });
    }
};
