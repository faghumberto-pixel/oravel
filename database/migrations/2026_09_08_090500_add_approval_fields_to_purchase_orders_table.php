<?php

use App\Models\PurchaseOrder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PurchaseOrder ganha aprovacao propria (Compras Fase 3): rascunho ->
 * aguardando_aprovacao -> aprovada -> enviada_fornecedor ->
 * parcialmente_recebida -> recebida / cancelada. O antigo status "aberta"
 * significava "confirmada e aguardando recebimento", que e' exatamente o
 * novo "enviada_fornecedor" -- migramos os dados existentes em vez de
 * conviver com dois nomes pro mesmo estado (ver
 * App\Models\PurchaseOrder::STATUS_ABERTA, removido).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignUuid('approved_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->timestamp('sent_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('sent_at');
        });

        DB::table('purchase_orders')->where('status', 'aberta')->update(['status' => 'enviada_fornecedor']);
    }

    public function down(): void
    {
        DB::table('purchase_orders')->where('status', 'enviada_fornecedor')->update(['status' => 'aberta']);
        DB::table('purchase_orders')->whereIn('status', ['rascunho', 'aguardando_aprovacao', 'aprovada'])->update(['status' => 'aberta']);

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn(['approved_at', 'sent_at', 'rejection_reason']);
        });
    }
};
