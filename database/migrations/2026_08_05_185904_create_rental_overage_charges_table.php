<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_overage_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('hours_used', 10, 2);
            $table->decimal('hours_included', 10, 2);
            $table->decimal('hours_overage', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->foreignUuid('account_receivable_id')->nullable()->constrained('account_receivables')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['contract_id', 'period_start']);
            $table->unique(['contract_id', 'asset_id', 'period_start', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_overage_charges');
    }
};
