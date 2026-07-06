<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ChecklistGroup::$fillable ja declarava 'description' mas a coluna
     * nunca existiu -- checklist_groups estava 0 registros/Resource oculto,
     * agora vira o cadastro real de "Grupo" do Ativo.
     */
    public function up(): void
    {
        Schema::table('checklist_groups', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_groups', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
