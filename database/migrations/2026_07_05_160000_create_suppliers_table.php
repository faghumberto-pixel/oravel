<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema real batendo com o que SupplierResource ja coleta no form
     * (identificacao, dados bancarios, homologacao/compliance, certidoes) --
     * a tabela nunca existiu apesar do Resource ja estar no ar.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('bank_account_pix')->nullable();
            $table->boolean('compliance_ceis_cnep')->default(false);
            $table->boolean('lista_trabalho_escravo')->default(false);
            $table->boolean('termo_lgpd')->default(false);
            $table->string('cnpj_card')->nullable();
            $table->string('inscricao_estadual')->nullable();
            $table->string('contrato_social')->nullable();
            $table->string('cnd_federal')->nullable();
            $table->string('crf_fgts')->nullable();
            $table->string('cndt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
