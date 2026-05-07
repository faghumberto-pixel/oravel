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
        Schema::create('checklist_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            
            // Adicionado índice para otimizar as buscas por empresa (tenant)
            // Isso é vital para a performance conforme seu volume de dados crescer
            $table->uuid('tenant_id')->index();
            
            $table->timestamps();
            
            // Opcional: Se você quiser garantir que não existam grupos com o mesmo nome 
            // para o mesmo tenant, pode adicionar uma constraint:
            // $table->unique(['name', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_groups');
    }
};
