<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pra qual filial e' a entrega -- ate aqui um recebimento so incrementava
 * o saldo unico do tenant, sem dizer onde o material fisicamente entrou.
 * Nullable: recebimentos antigos ficam sem filial (nao viram material
 * "orfao" no MaterialStockService, so nao contam pra nenhuma filial
 * especifica no saldo por local -- ver EditGoodsReceiptItem).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignUuid('internal_unit_id')->nullable()->after('purchase_order_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('internal_unit_id');
        });
    }
};
