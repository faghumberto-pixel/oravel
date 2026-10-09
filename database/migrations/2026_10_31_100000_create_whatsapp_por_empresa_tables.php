<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Número de WhatsApp (API oficial da Meta) de CADA empresa.
        Schema::create('tenant_whatsapp_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->string('waba_id')->nullable();
            $table->text('access_token');            // criptografado
            $table->text('app_secret');              // criptografado (assina o webhook)
            $table->string('verify_token');          // gerado pelo sistema, vai no cadastro do webhook na Meta
            $table->string('template_abertura')->nullable();  // modelo aprovado para iniciar conversa (1 variável: nome)
            $table->string('template_proposta')->nullable();  // modelo aprovado para enviar proposta (2 variáveis: nome, link)
            $table->string('template_language')->default('pt_BR');
            $table->timestamp('last_test_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        // Cada usuário da empresa pode ter o SEU número; user_id nulo = número da empresa.
        Schema::create('whatsapp_numeros', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone_number_id')->unique();
            $table->string('display_phone')->nullable();
            $table->string('rotulo')->nullable();
            $table->text('access_token')->nullable();   // criptografado; vazio = usa o token da empresa
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_test_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('whatsapp_conversas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('numero_id')->constrained('whatsapp_numeros')->cascadeOnDelete();
            $table->string('telefone');              // só dígitos, com DDI (ex.: 5519999332615)
            $table->string('nome')->nullable();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('crm_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignUuid('responsavel_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ultima_mensagem_em')->nullable();
            $table->timestamp('ultima_recebida_em')->nullable(); // abre a janela de 24h para texto livre
            $table->unsignedInteger('nao_lidas')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'numero_id', 'telefone']);
            $table->index(['tenant_id', 'ultima_mensagem_em']);
        });

        Schema::create('whatsapp_mensagens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('conversa_id')->constrained('whatsapp_conversas')->cascadeOnDelete();
            $table->string('direcao');               // entrada | saida
            $table->string('tipo')->default('texto'); // texto | modelo | outro
            $table->text('corpo')->nullable();
            $table->string('wa_id')->nullable();
            $table->string('status')->default('enviando'); // recebida | enviando | enviada | entregue | lida | falhou
            $table->text('erro')->nullable();
            $table->foreignUuid('enviada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('related_type')->nullable();
            $table->uuid('related_id')->nullable();
            $table->timestamps();

            $table->index(['conversa_id', 'created_at']);
            $table->unique(['tenant_id', 'wa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_mensagens');
        Schema::dropIfExists('whatsapp_conversas');
        Schema::dropIfExists('whatsapp_numeros');
        Schema::dropIfExists('tenant_whatsapp_settings');
    }
};
