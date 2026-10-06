<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plano de troca do veiculo: vence o que ocorrer primeiro, km ou dias.
        Schema::create('frota_planos_oleo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedInteger('intervalo_km')->nullable();
            $table->unsignedInteger('intervalo_dias')->nullable();
            $table->string('especificacao_oleo')->nullable();          // viscosidade / norma (ex.: 15W40 CK-4)
            $table->unsignedInteger('capacidade_litros')->nullable();
            $table->text('observacao_filtros')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id']);
        });
        DB::statement('CREATE UNIQUE INDEX frota_plano_oleo_ativo_unico ON frota_planos_oleo (ativo_id) WHERE ativo = true');

        // Destinacao do oleo usado (Resolucao CONAMA 362): coleta por empresa autorizada.
        Schema::create('frota_coletas_oleo_usado', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('coletada_em');
            $table->unsignedInteger('litros');
            $table->string('empresa_coletora');
            $table->string('numero_documento')->nullable();            // MTR / certificado de coleta
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'coletada_em']);
        });

        Schema::create('frota_trocas_oleo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->string('tipo', 12);                                // troca | reposicao
            $table->unsignedInteger('odometro');
            $table->unsignedInteger('litros');
            $table->string('produto');
            $table->string('lote')->nullable();
            $table->decimal('custo', 12, 2)->nullable();
            $table->foreignUuid('realizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('realizado_em');
            $table->unsignedInteger('proxima_troca_odometro')->nullable();
            $table->date('proxima_troca_data')->nullable();
            $table->text('observacoes')->nullable();
            // Ligacao FUTURA com a baixa de estoque de insumos (Itens Agregados guarda quantidade inteira; oleo usa litros).
            $table->uuid('saida_item_agregado_id')->nullable();
            $table->foreignUuid('coleta_oleo_usado_id')->nullable()->constrained('frota_coletas_oleo_usado')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'realizado_em']);
            $table->index(['tenant_id', 'tipo', 'coleta_oleo_usado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_trocas_oleo');
        Schema::dropIfExists('frota_coletas_oleo_usado');
        DB::statement('DROP INDEX IF EXISTS frota_plano_oleo_ativo_unico');
        Schema::dropIfExists('frota_planos_oleo');
    }
};
