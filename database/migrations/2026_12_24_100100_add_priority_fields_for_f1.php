<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F1: prioridade padrao por nivel de criticidade, origem (auto/manual) da prioridade da OS e indice para filtrar.
 * priority_source fica NULO nas OS ja existentes (legado: 'media' era so' o default da coluna).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('criticality_levels', function (Blueprint $table) {
            $table->string('default_priority', 40)->nullable();
        });

        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->string('priority_source', 10)->nullable();
            $table->index(['tenant_id', 'priority'], 'maintenance_orders_tenant_priority_idx');
        });

        DB::table('criticality_levels')->where('is_urgent', true)->update(['default_priority' => 'alta']);
        DB::table('criticality_levels')->whereNull('default_priority')->whereIn(DB::raw('lower(code)'), ['b', 'media', 'média'])->update(['default_priority' => 'media']);
        DB::table('criticality_levels')->whereNull('default_priority')->update(['default_priority' => 'baixa']);
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropIndex('maintenance_orders_tenant_priority_idx');
            $table->dropColumn('priority_source');
        });

        Schema::table('criticality_levels', function (Blueprint $table) {
            $table->dropColumn('default_priority');
        });
    }
};
