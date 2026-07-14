<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ordem de Compra formal. material_request_id/material_request_quotation_id
 * sao nullable -- permite Ordem de Compra avulsa, sem passar por uma
 * Requisicao formal (compra direta), embora o caminho normal seja
 * requisicao aprovada -> cotacao selecionada -> esta Ordem de Compra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('material_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('material_request_quotation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('supplier_id')->constrained();
            $table->string('status')->default('aberta');
            $table->decimal('total_value', 15, 2)->default(0);
            $table->date('expected_delivery_date')->nullable();
            $table->foreignUuid('created_by_user_id')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
