<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Intelligence — análise por equipamento (F1). Cada snapshot pode ter uma linha por equipamento (o que a empresa enviou) e o
     * resultado calculado dela (gravado, para o histórico não mudar quando uma regra mudar). Imutáveis como o snapshot.
     */
    public function up(): void
    {
        Schema::create('intelligence_equipamentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('snapshot_id')->constrained('intelligence_snapshots')->cascadeOnDelete();
            $table->string('patrimonio', 80);
            $table->string('nome', 120);
            $table->string('categoria', 80)->nullable();
            $table->unsignedSmallInteger('ano_fabricacao')->nullable();
            $table->decimal('valor', 14, 2)->nullable();
            $table->decimal('receita', 14, 2)->nullable();
            $table->decimal('custo_mes', 14, 2)->nullable();
            $table->decimal('custo_acumulado', 14, 2)->nullable();
            $table->unsignedInteger('os_corretivas')->nullable();
            $table->unsignedInteger('os_preventivas')->nullable();
            $table->unsignedInteger('reincidencias')->nullable();
            $table->unsignedSmallInteger('dias_locado')->nullable();
            $table->unsignedSmallInteger('dias_indisponivel')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['snapshot_id', 'patrimonio']);
            $table->index(['tenant_id', 'patrimonio']);
        });

        Schema::create('intelligence_equipamento_analises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('snapshot_id')->constrained('intelligence_snapshots')->cascadeOnDelete();
            $table->foreignUuid('equipamento_id')->unique()->constrained('intelligence_equipamentos')->cascadeOnDelete();
            $table->string('patrimonio', 80);
            $table->decimal('utilizacao_pct', 7, 2)->nullable();
            $table->decimal('disponibilidade_pct', 7, 2)->nullable();
            $table->decimal('retorno_anual_pct', 9, 2)->nullable();
            $table->decimal('razao_custo_valor', 9, 4)->nullable();
            $table->decimal('custo_sobre_receita_pct', 9, 2)->nullable();
            $table->decimal('reincidencia_pct', 7, 2)->nullable();
            $table->unsignedSmallInteger('idade_anos')->nullable();
            $table->string('classificacao', 20);
            $table->text('motivo');
            $table->string('tendencia', 12)->nullable();
            $table->unsignedSmallInteger('versao_regras');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'snapshot_id', 'classificacao'], 'intelligence_eq_analises_classe');
            $table->index(['tenant_id', 'patrimonio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_equipamento_analises');
        Schema::dropIfExists('intelligence_equipamentos');
    }
};
