<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada envio do teste público de treinamento (/treinamento/...). Tabela
     * global: quem responde é pessoa de fora do sistema, sem tenant nem login.
     */
    public function up(): void
    {
        Schema::create('treinamento_resultados', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('treinamento', 80)->index();
            $table->string('nome', 120);
            $table->string('funcao', 120);
            $table->unsignedSmallInteger('acertos');
            $table->unsignedSmallInteger('total');
            $table->decimal('nota', 3, 1);
            $table->boolean('aprovado')->index();
            $table->json('respostas');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treinamento_resultados');
    }
};
