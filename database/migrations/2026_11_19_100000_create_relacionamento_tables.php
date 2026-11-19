<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modulo "Relacionamento Oravel" (Central): tudo que uma empresa/pessoa faz no ecossistema da Oravel
 * (oravel.org, WhatsApp, contatos comerciais) vira uma linha em rel_interacoes. Tabelas GLOBAIS (sem
 * tenant_id): inteligencia comercial interna da Oravel, so' super admin na Central.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pessoa identificada (deixou e-mail ou telefone). Pode ou nao estar ligada a uma empresa.
        Schema::create('rel_contatos', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('nome', 160)->nullable();
            $t->string('email', 200)->nullable()->unique();
            $t->string('telefone', 40)->nullable();
            $t->string('telefone_digitos', 15)->nullable()->index();
            $t->string('empresa_nome', 200)->nullable();
            $t->string('cidade', 120)->nullable();
            $t->string('estado', 10)->nullable();
            $t->foreignUuid('prospect_id')->nullable()->constrained('prospects')->nullOnDelete();
            $t->foreignUuid('sales_lead_id')->nullable()->constrained('sales_leads')->nullOnDelete();
            $t->jsonb('visitor_tokens')->nullable(); // liga o acesso anonimo (t.js) a pessoa, depois de identificada
            $t->timestamp('consentimento_em')->nullable(); // base LGPD do rastreio
            $t->string('consentimento_origem', 80)->nullable();
            $t->timestamps();
        });

        // Catalogo de conteudos (artigo, video, ferramenta, pdf) -- base das recomendacoes (Fase 5).
        Schema::create('rel_conteudos', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('tipo', 20);
            $t->string('slug', 160)->unique();
            $t->string('titulo', 200);
            $t->string('tema', 60)->nullable();
            $t->string('url', 500)->nullable();
            $t->boolean('ativo')->default(true);
            $t->timestamps();
        });

        // Fonte unica da linha do tempo, do score e da inteligencia de mercado.
        Schema::create('rel_interacoes', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('contato_id')->nullable()->constrained('rel_contatos')->nullOnDelete();
            $t->foreignUuid('prospect_id')->nullable()->constrained('prospects')->nullOnDelete();
            $t->foreignUuid('sales_lead_id')->nullable()->constrained('sales_leads')->nullOnDelete();
            $t->string('tipo', 30);
            $t->string('conteudo_slug', 160)->nullable()->index();
            $t->string('titulo', 255)->nullable();
            $t->string('resultado', 255)->nullable();
            $t->unsignedInteger('duracao_segundos')->nullable();
            $t->string('origem', 80)->nullable();
            $t->string('utm_source', 200)->nullable();
            $t->string('utm_medium', 200)->nullable();
            $t->string('utm_campaign', 200)->nullable();
            $t->jsonb('dados')->nullable();
            $t->timestamp('ocorrido_em')->index();
            $t->string('dedupe_key', 120)->nullable()->unique(); // idempotencia (reenvio do site, backfill repetido)
            $t->timestamps();

            $t->index(['prospect_id', 'ocorrido_em']);
            $t->index(['sales_lead_id', 'ocorrido_em']);
            $t->index(['contato_id', 'ocorrido_em']);
            $t->index(['tipo', 'ocorrido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rel_interacoes');
        Schema::dropIfExists('rel_conteudos');
        Schema::dropIfExists('rel_contatos');
    }
};
