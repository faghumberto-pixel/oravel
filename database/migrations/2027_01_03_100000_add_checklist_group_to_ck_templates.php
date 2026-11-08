<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O que ativa um checklist na OS e o GRUPO DE CHECKLIST do ativo (assets.checklist_group_id), nao o grupo de equipamento.
 * O template passa a poder ser ligado a um grupo de checklist. Aditiva e nula: sem vinculo, vale o grupo de equipamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ck_templates', function (Blueprint $table) {
            $table->foreignUuid('checklist_group_id')->nullable()->after('grupo_id')->constrained('checklist_groups')->nullOnDelete();
            $table->index(['tenant_id', 'checklist_group_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::table('ck_templates', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'checklist_group_id', 'ativo']);
            $table->dropConstrainedForeignId('checklist_group_id');
        });
    }
};
