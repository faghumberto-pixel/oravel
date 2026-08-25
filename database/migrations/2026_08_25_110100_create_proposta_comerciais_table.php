<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proposta comercial (equipamento e/ou serviço), criada pelo vendedor
     * de campo (wizard mobile) e revisada pelo time Comercial -- distinta
     * de Quote (orçamento de peça/avaria, aprovado pelo CLIENTE final, vira
     * conta a receber). client_id nullable aqui de propósito: o vendedor
     * pode montar a proposta só com o CrmLead ainda não convertido, o
     * cliente só vira obrigatório em enviarParaComercial().
     */
    public function up(): void
    {
        Schema::create('proposta_comerciais', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignUuid('crm_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignUuid('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('seller_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status')->default('rascunho');
            $table->date('valid_until')->nullable();
            $table->text('terms')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('total_value', 15, 2)->default(0);

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // Preenchida só quando aprovada -- é o "acionamento" do
            // equipamento/serviço (ver PropostaComercial::criarSolicitacaoLocacao()).
            $table->foreignUuid('solicitacao_locacao_id')->nullable()->constrained('solicitacoes_locacao')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposta_comerciais');
    }
};
