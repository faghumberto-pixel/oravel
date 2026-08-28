<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * criticality_level_id foi criada sem cascadeOnDelete() (2026-05-04) --
 * travava DELETE de Tenant sempre que a exclusão em cascata tentava
 * remover CriticalityLevel antes das MaintenanceOrder que ainda a
 * referenciavam, mesmo com tenant_id em cascade. Achado ao limpar
 * tenants de teste em PROD 2026-08-28.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropForeign(['criticality_level_id']);
            $table->foreign('criticality_level_id')->references('id')->on('criticality_levels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropForeign(['criticality_level_id']);
            $table->foreign('criticality_level_id')->references('id')->on('criticality_levels');
        });
    }
};
