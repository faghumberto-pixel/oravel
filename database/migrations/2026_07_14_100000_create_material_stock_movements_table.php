<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historico formal de entrada/saida de estoque -- ate 2026-07-14 o unico
 * jeito de saber "por que o estoque de um Material mudou" era olhar
 * MaintenanceOrderMaterial (saida por consumo) espalhado, sem nenhum
 * ledger unificado. Um arquivo chamado "create_stock_movements_table"
 * ja existia (2026_05_04_012400) mas por engano nunca criava essa tabela
 * de verdade (copy-paste de outra migration) -- por isso o nome novo
 * (material_stock_movements), pra nao colidir/confundir com aquele
 * arquivo morto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('quantity', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->nullableUuidMorphs('reference');
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_movements');
    }
};
