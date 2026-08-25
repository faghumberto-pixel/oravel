<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cabeçalho de termos padrão, tenant-scoped -- mesmo formato de
     * ChecklistTemplate (nome + is_active). Ao criar uma PropostaComercial,
     * default_terms é COPIADO pra terms, não referenciado -- editar o
     * template depois não altera propostas já criadas.
     */
    public function up(): void
    {
        Schema::create('proposta_comercial_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('default_terms')->nullable();
            $table->unsignedInteger('default_valid_days')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposta_comercial_templates');
    }
};
