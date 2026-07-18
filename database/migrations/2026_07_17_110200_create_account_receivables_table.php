<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_receivables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('billing_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            // bill_category_id/cost_center_id ficam sem FK real: BillCategory
            // e CostCenter sao models existentes mas suas tabelas
            // (bill_categories/cost_centers) nunca tiveram migration --
            // mesmo gap ja documentado em AccountPayable. Colunas soltas
            // preservam o relacionamento logico sem quebrar a migration.
            $table->uuid('bill_category_id')->nullable();
            $table->uuid('cost_center_id')->nullable();

            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->date('payment_date')->nullable();
            $table->string('status')->default('pendente');
            $table->decimal('multa_percentual', 5, 2)->nullable();
            $table->decimal('multa_valor', 12, 2)->nullable();
            $table->string('mes', 2)->nullable();
            $table->string('ano', 4)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_receivables');
    }
};
