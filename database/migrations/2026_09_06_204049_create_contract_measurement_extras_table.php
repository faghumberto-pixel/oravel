<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Taxas adicionais de uma medição (mobilização, desmobilização, outras)
 * -- itens de linha separados em vez de um único valor solto em
 * contract_measurements.total_extras_amount, pra manter rastreável o que
 * compõe o total quando o financeiro revisa a medição.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_measurement_extras', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contract_measurement_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_measurement_extras');
    }
};
