<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato de fornecimento -- vinculo opcional de um fornecedor a um
 * contrato com vigencia (prazo, valor, condicoes de pagamento). Model
 * dedicado, nao reaproveita App\Models\Contract: aquele e' moldado pra
 * locacao a cliente (client_id, billing_type por hora/franquia,
 * horimetro/odometro inicial) -- conceito diferente de compra a
 * fornecedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number');
            $table->text('object')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('value', 15, 2)->nullable();
            $table->string('payment_terms')->nullable();
            $table->string('status')->default('ativo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_contracts');
    }
};
