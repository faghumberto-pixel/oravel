<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MaintenancePlan ate aqui so existia por Ativo individual (asset_id
 * obrigatorio). Modulo de Preventiva por Grupo (Gerador, Compressor, etc.)
 * reaproveita a mesma tabela: um plano agora pode ser um item de TEMPLATE
 * do Grupo (checklist_group_id preenchido, asset_id nulo -- vale pra todo
 * ativo daquele grupo) OU continuar por Ativo especifico como ja era.
 * "last_service_hours" so faz sentido pro caso legado por-Ativo; pra
 * templates de grupo o "ultimo servico" e calculado por Ativo a partir de
 * PreventiveMaintenanceExecution (ver dueStatusForAsset() no model).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->foreignUuid('asset_id')->nullable()->change();
            $table->foreignUuid('checklist_group_id')->nullable()->after('asset_id')->constrained()->cascadeOnDelete();
            $table->string('notes')->nullable()->after('interval_hours');
        });

        DB::statement(
            'ALTER TABLE maintenance_plans ADD CONSTRAINT maintenance_plans_asset_or_group_check '
            .'CHECK (asset_id IS NOT NULL OR checklist_group_id IS NOT NULL)'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE maintenance_plans DROP CONSTRAINT IF EXISTS maintenance_plans_asset_or_group_check');

        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checklist_group_id');
            $table->dropColumn('notes');
            $table->foreignUuid('asset_id')->nullable(false)->change();
        });
    }
};
