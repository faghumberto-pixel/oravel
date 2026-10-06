<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_baterias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->unsignedSmallInteger('amperagem_ah')->nullable();   // capacidade (Ah)
            $table->unsignedSmallInteger('cca')->nullable();            // corrente de partida a frio
            $table->string('numero_serie')->nullable();
            $table->date('comprada_em')->nullable();
            $table->date('garantia_ate')->nullable();
            $table->decimal('custo', 12, 2)->nullable();
            $table->string('situacao', 20)->default('estoque');         // estoque | montada | sucateada
            $table->timestamps();
            $table->index(['tenant_id', 'situacao']);
        });

        // Teste de tensao: sem tenant_id (isolamento pela bateria / veiculo). bateria_id nulo = tensao medida no
        // checklist em veiculo com 0 ou 2+ baterias (nao da para saber qual).
        Schema::create('frota_testes_bateria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('bateria_id')->nullable()->constrained('frota_baterias')->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->nullable()->constrained('assets')->cascadeOnDelete();   // nulo = bateria testada em estoque
            $table->foreignUuid('checklist_id')->nullable()->constrained('frota_checklists')->nullOnDelete();
            $table->decimal('tensao', 5, 2);                            // volts
            $table->unsignedInteger('odometro')->nullable();
            $table->timestamp('testado_em');
            $table->timestamps();
            $table->index(['ativo_id', 'testado_em']);
            $table->index(['bateria_id', 'testado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_testes_bateria');
        Schema::dropIfExists('frota_baterias');
    }
};
