<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo de Material POR FILIAL (InternalUnit) -- ate aqui Material.current_stock
 * era um pool unico por tenant inteiro. Material.current_stock continua
 * existindo como cache agregado (soma de todas as linhas daqui), pra nao
 * quebrar leitura ja existente (isLowStock(), coloracao de tabela, gatilho
 * de PartsRequest) -- ver App\Services\MaterialStockService, ponto unico
 * de escrita dali pra frente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_location_stock', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('internal_unit_id')->constrained()->cascadeOnDelete();
            $table->integer('current_quantity')->default(0);
            $table->integer('minimum_threshold')->default(0);
            $table->integer('maximum_threshold')->nullable();
            $table->timestamps();

            $table->unique(['material_id', 'internal_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_location_stock');
    }
};
