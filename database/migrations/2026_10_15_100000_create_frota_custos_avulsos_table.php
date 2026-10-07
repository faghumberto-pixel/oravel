<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Custos do veículo que não vêm de outras telas (seguro, IPVA, licenciamento, pedágio, lavagem...), com rateio em meses. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_custos_avulsos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->string('tipo', 20);                                // seguro | ipva | licenciamento | tacografo | pedagio | lavagem | estacionamento | outro
            $table->date('data');                                      // data do pagamento (início do rateio)
            $table->decimal('valor', 12, 2);
            $table->unsignedSmallInteger('rateio_meses')->default(1);  // 12 = anual dividido em 12 partes, a partir do mês da data
            $table->string('descricao')->nullable();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_custos_avulsos');
    }
};
