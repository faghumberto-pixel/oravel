<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Onboarding guiado: progresso por passo, início/conclusão por solução e log de lembretes. Só cria tabelas novas. */
    public function up(): void
    {
        Schema::create('onboarding_progresso', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('solution', 40);
            $table->string('step_key', 60);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'solution', 'step_key']);
        });

        Schema::create('onboarding_inicios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('solution', 40);
            $table->timestamp('iniciado_em');            // momento da assinatura (criação da empresa)
            $table->timestamp('primeiro_acesso_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamp('dispensado_em')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'solution']);
        });

        Schema::create('onboarding_lembretes_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('solution', 40);
            $table->string('lembrete', 40);
            $table->timestamp('enviado_em');
            $table->timestamps();
            $table->unique(['tenant_id', 'solution', 'lembrete']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_lembretes_log');
        Schema::dropIfExists('onboarding_inicios');
        Schema::dropIfExists('onboarding_progresso');
    }
};
