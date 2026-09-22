<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico de inspeções/ensaios do equipamento NR-13 (interna, externa, de segurança,
 * hidrostática). data_proxima_inspecao é gravada na execução da inspeção (data_inspecao +
 * intervalo vindo de Nr13InspectionPeriodicity no momento do registro) -- não recalculada
 * dinamicamente, pelo mesmo motivo de EmployeeCertification.data_validade: é o valor "oficial"
 * daquele registro, e trocar a periodicidade depois não deve reescrever inspeções já feitas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nr13_inspections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->string('tipo'); // interna | externa | seguranca | hidrostatica
            $table->date('data_inspecao');
            $table->date('data_proxima_inspecao')->nullable();
            $table->string('resultado')->nullable(); // aprovado | reprovado | com_ressalva
            $table->string('responsavel_tecnico')->nullable();
            $table->string('numero_art')->nullable(); // Anotação de Responsabilidade Técnica
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'asset_id']);
            $table->index(['tipo', 'data_proxima_inspecao']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nr13_inspections');
    }
};
