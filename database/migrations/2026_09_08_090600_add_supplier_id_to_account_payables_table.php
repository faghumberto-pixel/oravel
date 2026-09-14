<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * account_payables nunca teve nenhum vinculo com fornecedor (nem campo
 * texto solto) -- campo aditivo, nullable: nem toda conta a pagar tem um
 * fornecedor (imposto, folha, etc. continuam so' com o texto livre em
 * description). goods_receipt_id (tambem nullable) e' o elo criado pela
 * acao "Gerar Conta a Pagar" em GoodsReceiptResource
 * (GoodsReceipt::generateAccountPayable()) -- so' serve pra idempotencia
 * (nao deixar gerar 2x a conta do mesmo recebimento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            $table->foreignUuid('supplier_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignUuid('goods_receipt_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropConstrainedForeignId('goods_receipt_id');
        });
    }
};
