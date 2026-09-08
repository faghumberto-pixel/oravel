<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Itemiza a MaterialRequestQuotation (antes so' guardava um total_value
 * agregado por fornecedor) -- necessario pra comparar preco por item
 * entre fornecedores e alimentar historico de preco por material/
 * fornecedor. total_value da cotacao passa a ser a soma destes itens
 * (App\Observers\MaterialRequestQuotationItemObserver), mesmo padrao ja
 * usado em Quote/QuoteItem::recalculateTotal().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_request_quotation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_request_quotation_id')->constrained('material_request_quotations')->cascadeOnDelete();
            $table->foreignUuid('material_request_item_id')->nullable()->constrained('material_request_items')->nullOnDelete();
            $table->foreignUuid('material_id')->constrained();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_request_quotation_items');
    }
};
