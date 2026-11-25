<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cadastro do teste grátis aguardando a confirmação do e-mail: só vira conta (tenant, usuário, lead) depois do clique no link. */
    public function up(): void
    {
        Schema::create('intelligence_trial_pendentes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->string('empresa');
            $table->string('whatsapp', 20)->index();      // E.164
            $table->string('email', 160)->index();         // minúsculo
            $table->string('token_hash', 64)->unique();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_trial_pendentes');
    }
};
