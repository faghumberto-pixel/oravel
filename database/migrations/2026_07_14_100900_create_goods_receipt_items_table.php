<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ao criar uma linha aqui (GoodsReceiptItemObserver::created()):
 * incrementa Material.current_stock, grava MaterialStockMovement tipo
 * entrada_compra, soma em purchase_order_items.quantity_received, e
 * recalcula PurchaseOrder.status. Nunca editada depois de criada (sem
 * UpdateAction na tela) pra manter o ledger confiavel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('purchase_order_item_id')->constrained();
            $table->decimal('quantity_received', 10, 2);
            $table->string('serial_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
