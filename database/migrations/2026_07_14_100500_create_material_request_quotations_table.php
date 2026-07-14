<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma cotacao de um fornecedor pra uma Requisicao de Compra
 * (MaterialRequest) -- varias cotacoes por requisicao, a escolhida
 * (is_selected) e' a que vira a Ordem de Compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_request_quotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained();
            $table->decimal('total_value', 15, 2);
            $table->unsignedInteger('delivery_days')->nullable();
            $table->string('payment_terms')->nullable();
            $table->boolean('is_selected')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_request_quotations');
    }
};
