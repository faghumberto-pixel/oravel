<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            
            // AJUSTADO: Mudamos para foreignUuid para casar com a chave primária UUID de tenants
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            
            // ID da cobrança gerada especificamente no Asaas (ex: pay_0000098765)
            $table->string('asaas_payment_id')->unique()->index();
            
            // Valores financeiros
            $table->decimal('value', 10, 2);
            $table->date('due_date'); // Data de vencimento
            
            // Status interno da cobrança
            $table->string('status')->default('pending'); // pending, received, overdue, refunded
            
            // Forma de pagamento que o cliente escolheu usar
            $table->string('payment_method')->nullable(); // pix, boleto, credit_card
            
            // Link público do Asaas caso o operador da Central precise copiar e mandar no WhatsApp do cliente
            $table->text('invoice_url')->nullable(); 

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};