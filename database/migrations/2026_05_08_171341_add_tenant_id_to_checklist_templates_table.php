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
        Schema::table('checklist_templates', function (Blueprint $table) {
            // Adiciona a coluna UUID que o Filament está exigindo
            // Usamos nullable() para evitar erros caso já existam registros na tabela
            $table->uuid('tenant_id')->nullable()->after('id')->index();
            
            // Adiciona os campos de dados que faltavam na versão anterior
            $table->string('name')->nullable()->after('tenant_id');
            $table->text('description')->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table) {
            $table->dropColumn(['tenant_id', 'name', 'description', 'is_active']);
        });
    }
};