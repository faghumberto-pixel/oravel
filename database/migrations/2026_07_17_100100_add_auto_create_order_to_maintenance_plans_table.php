<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opt-in explicito pra CheckMaintenanceDueAlerts criar a O.S. sozinho
 * quando o plano vence -- default false, planos ja cadastrados continuam
 * so' notificando (comportamento identico ao de hoje) ate o tenant ligar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->boolean('auto_create_order')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->dropColumn('auto_create_order');
        });
    }
};
