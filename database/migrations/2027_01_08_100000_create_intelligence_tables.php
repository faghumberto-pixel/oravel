<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Oravel Intelligence. Importação → Snapshot (imutável) → Indicadores → Diagnóstico; Benchmark agregado e anônimo (sem tenant_id).
     * Nada é sobrescrito: cada importação gera um snapshot novo e o histórico da empresa é a soma deles.
     */
    public function up(): void
    {
        Schema::create('intelligence_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origem', 30)->default('arquivo');
            $table->string('arquivo')->nullable();
            $table->string('status', 20)->default('pendente'); // pendente, processando, concluida, falhou
            $table->text('erro')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('intelligence_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('import_id')->unique()->constrained('intelligence_imports')->cascadeOnDelete();
            $table->date('data_referencia');
            $table->json('dados');
            $table->string('hash', 64);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'data_referencia']);
        });

        Schema::create('intelligence_indicators', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('snapshot_id')->constrained('intelligence_snapshots')->cascadeOnDelete();
            $table->string('chave', 80);
            $table->decimal('valor', 18, 4)->nullable();
            $table->string('unidade', 20)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['snapshot_id', 'chave']);
            $table->index(['tenant_id', 'chave']);
        });

        Schema::create('intelligence_diagnostics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('snapshot_id')->constrained('intelligence_snapshots')->cascadeOnDelete();
            $table->string('area', 30);
            $table->decimal('pontuacao', 5, 2);
            $table->string('faixa', 20)->nullable();
            $table->json('detalhes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['snapshot_id', 'area']);
            $table->index(['tenant_id', 'area']);
        });

        // Agregado anônimo entre empresas: SEM tenant_id de propósito (nunca guarda quem contribuiu).
        Schema::create('intelligence_benchmarks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('indicador', 80);
            $table->string('regiao', 40)->default('*');
            $table->string('porte', 40)->default('*');
            $table->string('segmento', 40)->default('*');
            $table->string('categoria', 60)->default('*');
            $table->date('periodo');
            $table->unsignedInteger('amostra');
            $table->decimal('media', 18, 4)->nullable();
            $table->decimal('p25', 18, 4)->nullable();
            $table->decimal('mediana', 18, 4)->nullable();
            $table->decimal('p75', 18, 4)->nullable();
            $table->timestamps();
            $table->unique(['indicador', 'regiao', 'porte', 'segmento', 'categoria', 'periodo'], 'intelligence_benchmarks_grupo_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_benchmarks');
        Schema::dropIfExists('intelligence_diagnostics');
        Schema::dropIfExists('intelligence_indicators');
        Schema::dropIfExists('intelligence_snapshots');
        Schema::dropIfExists('intelligence_imports');
    }
};
