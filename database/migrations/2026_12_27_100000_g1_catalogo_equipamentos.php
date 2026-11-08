<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G1 fase 1 -- catalogo de grupos de equipamento (dado por tenant). 100% ADITIVA: so adiciona colunas
 * anulaveis/com default em asset_categories e cria tabelas novas. Nenhum dado existente muda.
 * O "Grupo" e a propria asset_categories (is_active = "ativo" do Bloco 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_categories', function (Blueprint $table) {
            $table->string('medidor')->nullable();            // horimetro | km | ambos | dias
            $table->string('criticidade_padrao')->nullable(); // critica | alta | media | baixa
            $table->boolean('exige_operador')->default(false);
            $table->string('origem')->default('tenant');      // sistema | tenant
            $table->string('slug_template')->nullable();
            $table->unsignedInteger('versao_template')->nullable();
            $table->boolean('personalizado')->default(false);
            $table->unsignedInteger('ordem')->default(0);
            $table->softDeletes();
            $table->index(['tenant_id', 'slug_template']);
        });

        $base = function (Blueprint $table, bool $comGrupo = true): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            if ($comGrupo) {
                $table->foreignUuid('grupo_id')->constrained('asset_categories')->cascadeOnDelete();
            }
            $table->string('slug');
            $table->string('origem')->default('tenant');
            $table->string('slug_template')->nullable();
            $table->unsignedInteger('versao_template')->nullable();
            $table->boolean('personalizado')->default(false);
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();
            $table->softDeletes();
        };

        Schema::create('catalogo_itens', function (Blueprint $table) use ($base) {
            $base($table, false);
            $table->string('tipo'); // checklist | documento | inspecao | acessorio | operador
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->unsignedInteger('validade_dias')->nullable();
            $table->jsonb('parametros')->nullable();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('catalogo_atributos', function (Blueprint $table) use ($base) {
            $base($table);
            $table->string('chave');
            $table->string('rotulo');
            $table->string('tipo'); // texto | numero | selecao | booleano | data
            $table->string('unidade')->nullable();
            $table->boolean('obrigatorio')->default(false);
            $table->jsonb('opcoes')->nullable();
            $table->unique(['tenant_id', 'grupo_id', 'slug']);
        });

        Schema::create('catalogo_requisitos', function (Blueprint $table) use ($base) {
            $base($table);
            $table->foreignUuid('item_id')->constrained('catalogo_itens')->cascadeOnDelete();
            $table->string('efeito'); // IMP | ALR | DOC | INSP | ACESS
            $table->unsignedInteger('validade_dias')->nullable();
            $table->unsignedSmallInteger('tolerancia_pct')->nullable();
            $table->boolean('gera_os')->default(false);
            $table->boolean('validar_norma')->default(false); // dependente de norma: validar com o responsavel tecnico
            $table->string('norma')->nullable();
            $table->jsonb('parametros')->nullable();
            $table->unique(['tenant_id', 'grupo_id', 'slug']);
        });

        Schema::create('catalogo_preventivas', function (Blueprint $table) use ($base) {
            $base($table);
            $table->string('nome');
            $table->string('medidor'); // horimetro | km | dias
            $table->unsignedInteger('intervalo');
            $table->unsignedSmallInteger('tolerancia_pct')->default(10); // ate X% = Apto com restricao; acima = Inapto
            $table->unsignedInteger('ou_dias')->nullable();
            $table->string('regra_ou')->default('primeiro'); // primeiro | ambos
            $table->jsonb('parametros')->nullable();
            $table->unique(['tenant_id', 'grupo_id', 'slug']);
        });

        Schema::create('catalogo_operadores', function (Blueprint $table) use ($base) {
            $base($table);
            $table->string('habilitacao');
            $table->string('norma')->nullable();
            $table->unsignedInteger('validade_dias')->nullable();
            $table->boolean('obrigatorio')->default(true);
            $table->boolean('validar_norma')->default(false);
            $table->unique(['tenant_id', 'grupo_id', 'slug']);
        });

        Schema::create('catalogo_pecas_criticas', function (Blueprint $table) use ($base) {
            $base($table);
            // A OS consome Material (MaintenanceOrderMaterial); por isso o vinculo e com materials, nao com parts.
            $table->foreignUuid('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('descricao');
            $table->jsonb('compatibilidade')->nullable();
            $table->unique(['tenant_id', 'grupo_id', 'slug']);
        });

        Schema::create('catalogo_sla', function (Blueprint $table) use ($base) {
            $base($table);
            $table->string('criticidade'); // critica | alta | media | baixa
            $table->unsignedInteger('resposta_min')->nullable();
            $table->unsignedInteger('resolucao_min')->nullable();
            $table->string('calendario')->default('comercial'); // comercial | 24x7
            $table->jsonb('pausa_por')->nullable();
            $table->unique(['tenant_id', 'grupo_id', 'slug']);
        });

        Schema::create('catalogo_regras', function (Blueprint $table) use ($base) {
            $base($table);
            $table->string('nome');
            $table->jsonb('condicao')->nullable();
            $table->string('efeito'); // restricao | inapto | bloqueia_alocacao
            $table->string('strategy')->nullable(); // so quando nao da para expressar em dados (ex.: nr13)
            $table->boolean('validar_norma')->default(false);
            $table->unique(['tenant_id', 'grupo_id', 'slug']);
        });

        Schema::create('catalogo_instalacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unique('tenant_id');
            $table->unsignedInteger('versao_instalada')->default(0);
            $table->timestamp('instalado_em')->nullable();
            $table->jsonb('diff_pendente')->nullable(); // preenchido na fase 6 (atualizacao de template)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['catalogo_instalacoes', 'catalogo_regras', 'catalogo_sla', 'catalogo_pecas_criticas', 'catalogo_operadores',
            'catalogo_preventivas', 'catalogo_requisitos', 'catalogo_atributos', 'catalogo_itens'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::table('asset_categories', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'slug_template']);
            $table->dropColumn(['medidor', 'criticidade_padrao', 'exige_operador', 'origem', 'slug_template',
                'versao_template', 'personalizado', 'ordem', 'deleted_at']);
        });
    }
};
