<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Medição" mensal consolidada do contrato -- valor base (proporcional se
 * houve entrada/saída no meio do período) + excedente de franquia de
 * horas (reaproveita App\Domain\Fleet\Models\RentalOverageCharge/
 * ContractOverageCalculator já existentes, não duplica o cálculo de
 * horímetro) + taxas extras (mobilização/desmobilização, ver
 * contract_measurement_extras) = total_amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_measurements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contract_id')->constrained()->cascadeOnDelete();

            $table->date('reference_period_start');
            $table->date('reference_period_end');

            // Dias corridos do período de referência (fim - início + 1) e
            // quantos desses dias o contrato de fato esteve vigente --
            // iguais quando não há pró-rata (contrato já vigente no início
            // e ainda ativo no fim do período).
            $table->unsignedSmallInteger('total_days_in_period');
            $table->unsignedSmallInteger('prorated_days');

            $table->decimal('total_base_amount', 12, 2)->default(0);
            $table->decimal('total_excess_hours_amount', 12, 2)->default(0);
            $table->decimal('total_extras_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);

            // Link opcional pro excedente já calculado por
            // ContractOverageCalculator -- null quando o contrato não é
            // billing_type=franquia_excedente (só cobrança base+extras).
            $table->foreignUuid('rental_overage_charge_id')->nullable()
                ->constrained('rental_overage_charges')->nullOnDelete();

            $table->string('status')->default('draft');
            $table->text('rejection_reason')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignUuid('account_receivable_id')->nullable()
                ->constrained('account_receivables')->nullOnDelete();

            $table->timestamps();

            // Mesmo dedupe de rental_overage_charges: rodar o gerador 2x
            // pro mesmo contrato/período não duplica a medição.
            $table->unique(['contract_id', 'reference_period_start', 'reference_period_end'], 'contract_measurements_period_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_measurements');
    }
};
