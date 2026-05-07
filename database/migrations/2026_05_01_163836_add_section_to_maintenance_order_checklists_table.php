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
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            // Adicionamos a coluna 'section' que o Resource está tentando salvar
            $table->string('section')->nullable()->after('checklist_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            // Remove a coluna caso precise desfazer a alteração
            $table->dropColumn('section');
        });
    }
};