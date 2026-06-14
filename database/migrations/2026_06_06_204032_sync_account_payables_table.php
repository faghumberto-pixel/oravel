<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            // Verifica e adiciona colunas conforme necessário
            if (!Schema::hasColumn('account_payables', 'description')) $table->string('description')->nullable();
            if (!Schema::hasColumn('account_payables', 'bill_category_id')) $table->uuid('bill_category_id')->nullable();
            if (!Schema::hasColumn('account_payables', 'amount')) $table->decimal('amount', 15, 2)->default(0);
            if (!Schema::hasColumn('account_payables', 'due_date')) $table->date('due_date')->nullable();
            if (!Schema::hasColumn('account_payables', 'status')) $table->string('status')->default('pendente');
            if (!Schema::hasColumn('account_payables', 'branch_id')) $table->uuid('branch_id')->nullable();
            if (!Schema::hasColumn('account_payables', 'cost_center_id')) $table->uuid('cost_center_id')->nullable();
            if (!Schema::hasColumn('account_payables', 'asset_id')) $table->uuid('asset_id')->nullable();
            if (!Schema::hasColumn('account_payables', 'mes')) $table->string('mes', 2)->nullable();
            if (!Schema::hasColumn('account_payables', 'ano')) $table->string('ano', 4)->nullable();
        });
    }

    public function down(): void
    {
        // Se necessário reverter
    }
};