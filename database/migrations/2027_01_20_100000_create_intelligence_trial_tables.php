<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Teste grátis do Oravel Intelligence: conta de teste, lembretes enviados, contratos aceitos e eventos de webhook já tratados. */
    public function up(): void
    {
        Schema::create('intelligence_trials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nome');
            $table->string('empresa');
            $table->string('whatsapp', 20)->unique();      // E.164 (+5519999999999)
            $table->string('access_token_hash', 64)->nullable()->index();
            $table->timestamp('access_token_at')->nullable();
            $table->string('status', 20)->default('trial')->index();
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable()->index();
            $table->timestamp('last_upload_at')->nullable();
            $table->json('extensions')->nullable();         // [{dias, motivo, por, em}]
            $table->string('plano_nome')->nullable();
            $table->decimal('plano_preco', 10, 2)->nullable();
            $table->timestamp('contracted_at')->nullable();
            $table->date('billing_starts_on')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->date('access_until')->nullable();       // cancelou: acesso até o fim do período pago
            $table->timestamp('overdue_since')->nullable();
            $table->string('asaas_customer_id')->nullable();
            $table->string('asaas_subscription_id')->nullable()->index();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('signup_ip', 45)->nullable();
            $table->text('signup_user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('intelligence_trial_lembretes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trial_id')->constrained('intelligence_trials')->cascadeOnDelete();
            $table->unsignedSmallInteger('marco');          // dia do teste (20, 27, 30)
            $table->string('canal', 20)->default('email');
            $table->timestamp('enviado_em');
            $table->unique(['trial_id', 'marco', 'canal']);
        });

        Schema::create('intelligence_contratos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('trial_id')->constrained('intelligence_trials')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('versao', 20);
            $table->string('hash', 64);
            $table->longText('texto');                      // texto exato aceito (o hash é dele)
            $table->string('razao_social');
            $table->string('cnpj', 18);
            $table->string('email');
            $table->string('responsavel');
            $table->string('forma_pagamento', 20);          // PIX, BOLETO, CREDIT_CARD, UNDEFINED
            $table->decimal('preco', 10, 2);
            $table->date('billing_starts_on');
            $table->timestamp('aceite_em');
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'aceite_em']);
        });

        Schema::create('intelligence_webhook_eventos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('chave', 120)->unique();          // id do evento do Asaas (ou evento+pagamento+status)
            $table->string('evento', 60);
            $table->foreignUuid('trial_id')->nullable()->constrained('intelligence_trials')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_webhook_eventos');
        Schema::dropIfExists('intelligence_contratos');
        Schema::dropIfExists('intelligence_trial_lembretes');
        Schema::dropIfExists('intelligence_trials');
    }
};
