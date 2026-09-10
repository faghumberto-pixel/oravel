<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadados de EPI sobre um Material -- 1:1, mesmo padrao de
 * AssetForkliftSpecification/AssetPlatformSpecification (especificacao
 * tecnica anexada a um cadastro generico ja existente). Cada tamanho/
 * variante (luva P/M/G, bota por numeracao) e' seu proprio Material com
 * sua propria linha aqui -- nao existe tabela de variante, o catalogo de
 * Material ja resolve isso (SKU proprio por tamanho).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epi_specifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('epi_type');
            $table->string('size_label')->nullable();

            $table->string('ca_number');
            $table->string('ca_manufacturer')->nullable();
            $table->date('ca_validade');

            $table->unsignedInteger('estimated_lifespan_days')->nullable();

            $table->string('default_ownership_mode')->default('definitiva');

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'ca_validade']);
            $table->index(['tenant_id', 'epi_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epi_specifications');
    }
};
