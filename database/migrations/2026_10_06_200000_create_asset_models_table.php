<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalogo de modelos do tenant (campo Modelo do ativo, com "+" para cadastrar): padroniza a
        // grafia ("Actros 2651", nao "actros2651"/"ACTROS 2651") sem trocar o texto guardado em assets.modelo.
        Schema::create('asset_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('fabricante')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_models');
    }
};
