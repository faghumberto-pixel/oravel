<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // 1. Remove as colunas antigas que causavam conflitos de ID/404 (se existirem)
            $columnsToDrop = [];
            foreach (['asset_category_id', 'criticality_level_id', 'checklist_group_id'] as $col) {
                if (Schema::hasColumn('assets', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }

            // 2. Adiciona os novos campos de texto para a lista padronizada via código
            if (!Schema::hasColumn('assets', 'asset_category')) {
                $table->string('asset_category')->nullable()->after('name');
            }
            if (!Schema::hasColumn('assets', 'criticality_level')) {
                $table->string('criticality_level')->nullable()->after('asset_category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Reverte a criação dos campos de texto
            $columnsToDrop = [];
            foreach (['asset_category', 'criticality_level'] as $col) {
                if (Schema::hasColumn('assets', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }

            // Adiciona as colunas originais de volta (caso precise dar rollback)
            if (!Schema::hasColumn('assets', 'asset_category_id')) {
                $table->foreignId('asset_category_id')->nullable();
            }
            if (!Schema::hasColumn('assets', 'criticality_level_id')) {
                $table->foreignId('criticality_level_id')->nullable();
            }
            if (!Schema::hasColumn('assets', 'checklist_group_id')) {
                $table->foreignId('checklist_group_id')->nullable();
            }
        });
    }
};
