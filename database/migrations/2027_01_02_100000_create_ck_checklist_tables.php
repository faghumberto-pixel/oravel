<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checklists por grupo de equipamento (C1). Tabelas NOVAS com prefixo ck_: o nome checklist_templates ja existe (modulo
 * "Checklists" antigo, id numerico, com dados em PROD) e nao e tocado aqui. Aditiva: nenhuma tabela existente muda.
 *
 * - ck_templates/ck_sections/ck_items: a fonte da verdade e o banco, por tenant. O YAML em database/seeders/checklists e so o ponto de partida.
 * - ck_executions/ck_execution_answers: guardam SNAPSHOT (versao do template e texto do item), entao editar o template nunca
 *   altera uma execucao antiga.
 * - tenant_id em TODAS as tabelas (inclusive secoes/itens/respostas): o isolamento nao depende de join com a tabela pai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ck_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('grupo_id')->nullable()->constrained('asset_categories')->nullOnDelete(); // grupo de equipamento (G1)
            $table->string('nome');
            $table->string('tipo', 20);                          // pre_uso | diario | periodico | saida | entrada
            $table->string('periodicidade', 20)->default('por_uso'); // por_uso | diaria | semanal | mensal | por_medidor
            $table->unsignedInteger('intervalo_horimetro_km')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->boolean('ativo')->default(true);
            $table->string('origem', 20)->default('customizado'); // padrao | customizado
            $table->string('referencia_manual')->nullable();      // norma/manual do conjunto (hipotese: validar com o responsavel tecnico)
            $table->text('observacoes')->nullable();
            $table->string('slug_template')->nullable();
            $table->unsignedInteger('versao_template')->nullable();
            $table->boolean('personalizado')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug_template']);     // idempotencia do instalador (NULL pode repetir)
            $table->index(['tenant_id', 'grupo_id', 'ativo']);
        });

        Schema::create('ck_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('template_id')->constrained('ck_templates')->cascadeOnDelete();
            $table->string('titulo');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->string('slug_template')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['template_id', 'slug_template']);
            $table->index(['template_id', 'ordem']);
        });

        Schema::create('ck_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained('ck_sections')->cascadeOnDelete();
            $table->text('descricao');
            // conforme_nao_conforme_na | numerico | texto | foto | selecao
            $table->string('tipo_resposta', 30)->default('conforme_nao_conforme_na');
            $table->string('unidade', 20)->nullable();            // para numerico (V, Hz, mm, km, h, bar, psi...)
            $table->jsonb('opcoes')->nullable();                  // para selecao
            $table->boolean('obrigatorio')->default(true);
            $table->boolean('critico')->default(false);           // critico reprovado torna o ativo inapto (C3)
            $table->boolean('exige_foto_se_nao_conforme')->default(false);
            $table->string('referencia_manual')->nullable();      // texto livre: norma ou capitulo do manual
            $table->boolean('validar_norma')->default(false);     // depende de norma: validar com o responsavel tecnico
            // Ligacao com a aptidao do G1: item critico reprovado grava cumprimento reprovado neste requisito (C3).
            $table->foreignUuid('catalogo_requisito_id')->nullable()->constrained('catalogo_requisitos')->nullOnDelete();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->boolean('ativo')->default(true);
            $table->string('slug_template')->nullable();
            $table->boolean('personalizado')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['section_id', 'slug_template']);
            $table->index(['section_id', 'ordem']);
        });

        Schema::create('ck_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('template_id')->nullable()->constrained('ck_templates')->nullOnDelete();
            $table->string('template_nome');                      // snapshot
            $table->unsignedInteger('versao_template');           // snapshot
            $table->foreignUuid('executor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('medidor', 10)->nullable();            // horimetro | km
            $table->decimal('leitura_medidor', 12, 1)->nullable(); // horimetro/km no momento
            $table->string('status', 30)->default('em_andamento'); // em_andamento | aprovado | aprovado_com_ressalva | reprovado
            $table->timestamp('iniciado_em');
            $table->timestamp('finalizado_em')->nullable();
            $table->longText('assinatura')->nullable();           // imagem (data URL), mesmo padrao da Frota
            $table->text('observacoes')->nullable();
            $table->foreignUuid('ordem_servico_id')->nullable()->constrained('maintenance_orders')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'asset_id', 'finalizado_em']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ck_execution_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('execution_id')->constrained('ck_executions')->cascadeOnDelete();
            $table->foreignUuid('item_id')->nullable()->constrained('ck_items')->nullOnDelete();
            // SNAPSHOT do item no momento da execucao
            $table->string('secao_titulo')->nullable();
            $table->text('item_descricao');
            $table->string('tipo_resposta', 30);
            $table->boolean('critico')->default(false);
            $table->string('referencia_manual')->nullable();
            // resposta
            $table->string('resposta', 20)->nullable();           // conforme | nao_conforme | na (nulo para numerico/texto/foto/selecao)
            $table->decimal('valor_numerico', 14, 3)->nullable();
            $table->text('valor_texto')->nullable();
            $table->jsonb('fotos')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['execution_id']);
            $table->index(['tenant_id', 'item_id']);
        });
    }

    public function down(): void
    {
        foreach (['ck_execution_answers', 'ck_executions', 'ck_items', 'ck_sections', 'ck_templates'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
