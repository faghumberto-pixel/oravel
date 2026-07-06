<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Grupo" do ativo -- conceito separado de asset_category ("Categoria e
     * Tipo"), usado para escolher o checklist basico padrao. Um
     * checklist_group_id ja existiu em assets antes (migration
     * 2026_04_28_125924) e foi removido em 2026_05_09 quando a categoria
     * virou string estatica -- esta e uma coluna nova, sem relacao com
     * aquele historico.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignUuid('checklist_group_id')->nullable()->after('asset_category')
                ->constrained('checklist_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checklist_group_id');
        });
    }
};
