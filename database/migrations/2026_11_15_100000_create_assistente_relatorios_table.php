<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relatorios compartilhaveis do Assistente (botao "Abrir relatorio" / WhatsApp):
 * guarda uma FOTO do que foi consultado (colunas + linhas) acessivel por um link
 * com codigo longo e aleatorio, sem login, por tempo limitado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistente_relatorios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token', 64)->unique();
            $table->uuid('tenant_id')->nullable()->index();
            $table->uuid('user_id')->nullable();
            $table->string('area', 30);
            $table->string('titulo', 255);
            $table->text('resumo')->nullable();
            $table->json('colunas');
            $table->json('linhas');
            $table->unsignedInteger('total_linhas')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistente_relatorios');
    }
};
