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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ex: Básico, Pro, Enterprise
            $table->decimal('price', 10, 2); // Valor do plano
            $table->string('billing_cycle')->default('monthly'); // monthly ou yearly
            
            // Campo para você listar as vantagens de cada plano (JSON ajuda na flexibilidade)
            $table->json('features')->nullable(); 
            
            // Controle para você desativar um plano sem precisar deletar
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};