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
        Schema::create('branches', function (Blueprint $table) {
            // Padrão Oravel: UUID como chave primária
            $table->uuid('id')->primary();
            
            // Identificação da Unidade/Filial
            $table->string('name');
            $table->text('description')->nullable();

            // Multi-tenancy: Vinculação obrigatória ao Tenant
            $table->foreignUuid('tenant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Controle de Registro e Auditoria
            $table->timestamps();
            $table->softDeletes(); // Necessário para a Trait SoftDeletes do Model
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};