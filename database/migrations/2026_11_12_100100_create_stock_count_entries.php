<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historico da contagem fisica: cada vez que alguem conta (ou reconta) um item fica um registro com data, hora e quem contou.
 * O valor "contado" do item na folha e' o do ultimo registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_count_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('stock_count_item_id')->constrained('stock_count_items')->cascadeOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->foreignUuid('counted_by_user_id')->nullable();
            $table->string('counted_by_name')->nullable(); // nome na hora da contagem (o historico nao muda se o usuario for renomeado)
            $table->string('source', 20)->default('web'); // web | celular
            $table->string('note')->nullable();
            $table->timestamp('counted_at');
            $table->timestamps();

            $table->index(['stock_count_item_id', 'counted_at']);
        });

        Schema::table('stock_count_items', function (Blueprint $table) {
            $table->foreignUuid('counted_by_user_id')->nullable();
            $table->string('counted_by_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_count_items', function (Blueprint $table) {
            $table->dropColumn(['counted_by_user_id', 'counted_by_name']);
        });
        Schema::dropIfExists('stock_count_entries');
    }
};
