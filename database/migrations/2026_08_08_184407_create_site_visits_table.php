<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sessao de visita ao site/painel -- diferente de user_activity_logs
     * (1 linha por PAGINA VISTA de usuario JA AUTENTICADO, tenant_id/user_id
     * obrigatorios, so' dentro de /admin). Aqui e' 1 linha por SESSAO DE
     * VISITA (upsert por request, nao insert por pagina), cobre visitante
     * anonimo, /central, /admin e rotas publicas por token -- por isso
     * tenant_id/user_id sao nullable. referrer/UTM sao capturados so' na
     * entrada da sessao (first-touch attribution) e nunca sobrescritos por
     * hits seguintes da mesma sessao. duration_seconds e' recalculado a cada
     * hit (last_activity_at - started_at), ao contrario de
     * user_activity_logs onde o tempo e' calculado em runtime na listagem.
     */
    public function up(): void
    {
        Schema::create('site_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('visitor_token', 64);
            $table->string('session_token', 64)->unique();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable();

            $table->text('referrer_url')->nullable();
            $table->string('referrer_host')->nullable();
            $table->string('landing_path');

            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('utm_term', 100)->nullable();
            $table->string('utm_content', 100)->nullable();

            $table->string('entry_panel', 20)->nullable();

            $table->unsignedInteger('page_views')->default(1);

            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'started_at']);
            $table->index(['started_at']);
            $table->index(['utm_source', 'utm_campaign']);
            $table->index(['referrer_host']);
            $table->index(['visitor_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visits');
    }
};
