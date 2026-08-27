<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proveniencia estruturada da OS -- ate aqui so existia texto solto na
 * description. 'pmp_auto' = criada por
 * CheckMaintenanceDueAlerts::createOrderAutomatically(); null = criada
 * manualmente (todo o historico existente). String livre, nao enum
 * fechado, pra caber origem nova sem migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->string('origin')->nullable()->after('maintenance_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
