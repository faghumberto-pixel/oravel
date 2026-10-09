<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Saída de acessório/insumo passa a poder apontar pro contrato e guardar o custo unitário
        // da época da saída (o preço de compra muda depois; o custo do contrato não pode mudar junto).
        Schema::table('aggregate_item_exits', function (Blueprint $table) {
            $table->foreignUuid('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->decimal('unit_cost', 12, 2)->nullable();
        });

        // Insumo vendido num contrato de serviço pode sair sem equipamento de destino (era NOT NULL no banco).
        DB::statement('ALTER TABLE aggregate_item_exits ALTER COLUMN asset_id DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('aggregate_item_exits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
            $table->dropColumn('unit_cost');
        });
    }
};
