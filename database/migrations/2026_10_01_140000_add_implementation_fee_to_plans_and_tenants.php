<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Taxa de implantação: cobrança única (não recorrente), em 1 ou 2 parcelas.
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('implementation_fee', 12, 2)->nullable();
            $table->unsignedTinyInteger('implementation_installments')->default(1);
        });

        // Valor/parcelas efetivos por tenant (podem divergir do contrato, como o mrr_value).
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('implementation_fee', 12, 2)->nullable();
            $table->unsignedTinyInteger('implementation_installments')->nullable();
        });

        // Uma linha por parcela, cada uma com sua cobrança avulsa na Asaas.
        Schema::create('implementation_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('installment_number');
            $table->unsignedTinyInteger('installments_total');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->string('asaas_payment_id')->nullable()->index();
            $table->string('status')->default('pendente');
            $table->text('invoice_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'installment_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_charges');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['implementation_fee', 'implementation_installments']);
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['implementation_fee', 'implementation_installments']);
        });
    }
};
