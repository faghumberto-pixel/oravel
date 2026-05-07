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
        // 1. Cria a tabela de categorias
        Schema::create('material_categories', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Usando UUID
            $table->string('name');
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->timestamps();
        });

        // 2. Adiciona a coluna category_id na tabela de materiais existente
        // Usamos 'after' para organizar melhor, se o seu banco suportar
        Schema::table('materials', function (Blueprint $table) {
            $table->foreignUuid('category_id')
                  ->nullable()
                  ->constrained('material_categories')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
        
        Schema::dropIfExists('material_categories');
    }
};