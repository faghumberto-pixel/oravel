<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_hour_franchises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contract_id')->constrained()->cascadeOnDelete();
            $table->decimal('included_hours_per_period', 10, 2);
            $table->string('period_type');
            $table->decimal('overage_rate_per_hour', 10, 2);
            $table->date('effective_from');
            $table->timestamps();

            $table->index(['contract_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_hour_franchises');
    }
};
