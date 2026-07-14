<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecalho de um Inventario (contagem fisica de estoque). Fica em
 * rascunho enquanto a contagem esta em andamento; ao finalizar, cada
 * item com diferenca gera um MaterialStockMovement tipo 'ajuste_manual'
 * (ver MaterialStockTake::finalize()) e o saldo do Material e' corrigido
 * pro valor contado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_stock_takes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('conducted_by_user_id')->constrained('users');
            $table->string('status')->default('rascunho');
            $table->text('notes')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_stock_takes');
    }
};
