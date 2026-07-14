<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uma linha de contagem dentro de um Inventario: quanto o sistema dizia
 * que tinha (snapshot no momento em que o item entrou na contagem) vs.
 * quanto foi contado fisicamente. A diferenca (contado - esperado) e'
 * que vira o ajuste ao finalizar o inventario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_stock_take_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_stock_take_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_id')->constrained();
            $table->decimal('expected_quantity', 10, 2);
            $table->decimal('counted_quantity', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['material_stock_take_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_take_items');
    }
};
