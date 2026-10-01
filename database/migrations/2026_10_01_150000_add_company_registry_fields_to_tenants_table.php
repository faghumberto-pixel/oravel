<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Dados cadastrais da empresa Contratante, exibidos no Contrato de Assinatura.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('razao_social')->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->string('natureza_juridica')->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('email_contato')->nullable();
            $table->string('representante_nome')->nullable();
            $table->string('representante_cpf', 20)->nullable();
            $table->string('representante_cargo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['razao_social', 'nome_fantasia', 'natureza_juridica', 'inscricao_estadual', 'email_contato', 'representante_nome', 'representante_cpf', 'representante_cargo']);
        });
    }
};
