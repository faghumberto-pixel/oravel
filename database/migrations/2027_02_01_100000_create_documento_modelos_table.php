<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Modelos de documento por empresa (orçamento, proposta, contrato): o jeito que o cliente já escreve e imprime os dele. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documento_modelos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 20); // orcamento | proposta | contrato
            $table->string('nome');
            $table->text('cabecalho')->nullable();
            $table->text('rodape')->nullable();
            $table->text('termos')->nullable();
            $table->unsignedSmallInteger('validade_dias')->nullable();
            $table->json('campos')->nullable(); // [{titulo, texto}]: seções ou cláusulas, na ordem
            $table->string('logo_path')->nullable();
            $table->string('arquivo_referencia')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documento_modelos');
    }
};
