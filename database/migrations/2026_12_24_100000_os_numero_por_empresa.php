<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** N.o da O.S. controlavel por empresa: prefixo e proximo numero configuraveis; unicidade passa a ser por empresa. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('os_prefix', 20)->nullable();
            $table->unsignedInteger('os_next_number')->nullable();
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropUnique('maintenance_orders_os_number_unique');
            $table->unique(['tenant_id', 'os_number'], 'maintenance_orders_tenant_os_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropUnique('maintenance_orders_tenant_os_number_unique');
            $table->unique('os_number', 'maintenance_orders_os_number_unique');
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['os_prefix', 'os_next_number']);
        });
    }
};
