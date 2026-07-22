<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extensão do MaintenancePlan existente pra cobrir o "catálogo de template
 * por categoria" pedido -- em vez de criar tabelas novas
 * (maintenance_plan_templates/asset_maintenance_items) paralelas ao que já
 * roda em produção (PainelCriticidade, EventosEFalhas, MaintenanceOrderResource,
 * MaintenancePlanResource), o template é um grupo de linhas MaintenancePlan
 * com checklist_group_id preenchido (mecanismo que já existe), e a "cópia
 * editável por ativo" é uma linha MaintenancePlan com asset_id preenchido e
 * o MESMO name do item do grupo (ver MaintenancePlan::applicableFor()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            // Item agora pode ser só-por-dias (interval_hours vazio) -- era
            // NOT NULL desde a migration original, quando só existia o
            // dimensionamento por hora.
            $table->integer('interval_hours')->nullable()->change();
            $table->integer('interval_days')->nullable()->after('interval_hours');
            $table->boolean('is_critical')->default(false)->after('notes');
            // 'template' = copiado de um item de grupo pro ativo (ver
            // MaintenancePlanResource\Pages\CopyFromGroupTemplate ação);
            // 'manual' = criado direto pelo usuário, sem origem num grupo.
            $table->string('source')->default('manual')->after('is_critical');
            // Complementa last_service_hours (já existia, base pro intervalo
            // em horas) -- necessário pra ter uma referência própria quando
            // o item usa interval_days em vez de interval_hours.
            $table->date('last_service_date')->nullable()->after('last_service_hours');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->dropColumn(['interval_days', 'is_critical', 'source', 'last_service_date']);
        });

        Schema::table('maintenance_plans', function (Blueprint $table) {
            $table->integer('interval_hours')->nullable(false)->change();
        });
    }
};
