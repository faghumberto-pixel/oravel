<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AccountPayable (Model + Resource + Policy) existia desde antes desta
 * rodada mas nunca teve migration -- toda tela "Contas a Pagar" dava 500
 * (relation "account_payables" does not exist) em qualquer query real.
 * Corrigido agora porque ficou mais visivel: esta rodada adicionou 2
 * Resources novos (Contas a Receber/Planos de Cobranca) no mesmo grupo de
 * navegacao "Financeiro".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_payables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('asset_id')->nullable()->constrained()->nullOnDelete();
            // bill_category_id/cost_center_id ficam sem FK real: BillCategory
            // e CostCenter sao models existentes mas suas tabelas
            // (bill_categories/cost_centers) nunca tiveram migration --
            // mesmo gap ja tratado em account_receivables.
            $table->uuid('bill_category_id')->nullable();
            $table->uuid('cost_center_id')->nullable();

            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->string('status')->default('pendente');
            $table->string('mes', 2)->nullable();
            $table->string('ano', 4)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_payables');
    }
};
