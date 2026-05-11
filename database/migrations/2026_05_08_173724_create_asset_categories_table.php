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
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            
            // Relacionamento com o Tenant (UUID conforme seu padrão)
            $table->uuid('tenant_id')->index();
            
            // Dados da Categoria
            $table->string('name'); // Ex: Geradores, Plataformas, Munks
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            
            // Status para controle
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Chave estrangeira para segurança dos dados
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};