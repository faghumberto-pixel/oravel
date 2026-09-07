<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * FK real pra rastrear qual O.S. motivou uma saída exit_work_order --
     * reference_document (já existente) é texto livre, sem integridade
     * referencial, insuficiente pra consultar "todas as saídas desta OS".
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignUuid('work_order_id')->nullable()->after('warehouse_id')
                ->constrained('maintenance_orders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_id');
        });
    }
};
