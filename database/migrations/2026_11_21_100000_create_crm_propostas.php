<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Propostas próprias do CRM (Fase 6D-2): independentes das propostas do ERP (que exigem Cliente e Ativos). */
    public function up(): void
    {
        Schema::create('crm_propostas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->foreignUuid('crm_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignUuid('crm_conta_id')->nullable()->constrained('crm_contas')->nullOnDelete();
            $table->foreignUuid('vendedor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('titulo');
            $table->date('validade_ate')->nullable();
            $table->text('condicoes')->nullable();
            $table->text('observacoes')->nullable();
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 15)->default('rascunho'); // rascunho | enviada | aceita | recusada | expirada
            $table->string('token', 64)->unique();
            $table->timestamp('enviada_em')->nullable();
            $table->timestamp('visualizada_em')->nullable();
            $table->timestamp('respondida_em')->nullable();
            $table->text('motivo_recusa')->nullable();
            $table->string('nome_aceite')->nullable();
            $table->string('ip_aceite', 45)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'numero']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('crm_proposta_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('crm_proposta_id')->constrained('crm_propostas')->cascadeOnDelete();
            $table->foreignUuid('crm_produto_id')->nullable()->constrained('crm_produtos')->nullOnDelete();
            $table->string('descricao');
            $table->decimal('quantidade', 12, 2)->default(1);
            $table->decimal('preco_unitario', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'crm_proposta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_proposta_itens');
        Schema::dropIfExists('crm_propostas');
    }
};
