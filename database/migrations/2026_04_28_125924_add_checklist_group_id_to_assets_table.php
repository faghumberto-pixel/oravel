<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Adiciona a coluna para vincular o ativo ao Grupo (Centro de Custo)
            $table->uuid('checklist_group_id')->nullable();
            
            // Define a chave estrangeira para garantir a integridade dos dados
            $table->foreign('checklist_group_id')
                  ->references('id')
                  ->on('checklist_groups')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['checklist_group_id']);
            $table->dropColumn('checklist_group_id');
        });
    }
};