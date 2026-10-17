<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complemento "Nota fiscal de serviço (NFS-e)": cadastro fiscal da empresa cliente (os tokens do emissor ficam criptografados;
     * o certificado A1 e a senha NUNCA são gravados aqui, só a validade) e as notas emitidas.
     */
    public function up(): void
    {
        Schema::create('tenant_fiscal_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('ambiente', 12)->default('homologacao'); // homologacao | producao
            $table->string('padrao', 12)->default('municipal');     // municipal | nacional
            $table->string('cnpj', 20)->nullable();
            $table->string('razao_social')->nullable();
            $table->string('nome_fantasia')->nullable();
            $table->string('inscricao_municipal', 30)->nullable();
            $table->string('inscricao_estadual', 30)->nullable();
            $table->string('regime_tributario', 20)->nullable();    // mei | simples | simples_excesso | normal
            $table->string('codigo_municipio', 10)->nullable();     // IBGE
            $table->string('cep', 10)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('municipio')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('email')->nullable();
            $table->string('telefone', 30)->nullable();
            $table->string('item_lista_servico', 20)->nullable();
            $table->string('codigo_tributario_municipio', 30)->nullable();
            $table->decimal('aliquota_iss', 5, 2)->nullable();
            $table->boolean('iss_retido')->default(false);
            $table->text('descricao_padrao')->nullable();
            $table->boolean('emissao_automatica')->default(false);
            $table->string('provider_company_id', 60)->nullable();
            $table->text('token_homologacao')->nullable();           // criptografado
            $table->text('token_producao')->nullable();              // criptografado
            $table->string('webhook_token', 64);
            $table->date('certificado_validade')->nullable();
            $table->timestamp('certificado_enviado_em')->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('account_receivable_id')->nullable()->index();
            $table->uuid('client_id')->nullable()->index();
            $table->uuid('contract_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('ref', 80)->unique();                      // idempotência no emissor
            $table->string('ambiente', 12);
            $table->string('status', 20)->default('rascunho');        // rascunho | processando | autorizada | erro | cancelada
            $table->decimal('valor', 14, 2);
            $table->json('tomador');
            $table->text('discriminacao')->nullable();
            $table->string('numero', 30)->nullable();
            $table->string('serie', 10)->nullable();
            $table->string('codigo_verificacao', 60)->nullable();
            $table->text('pdf_url')->nullable();
            $table->text('xml_url')->nullable();
            $table->text('erro')->nullable();
            $table->timestamp('emitida_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->text('cancelamento_motivo')->nullable();
            $table->timestamp('enviada_em')->nullable();
            $table->timestamps();
        });

        // Uma conta a receber só tem UMA nota viva (processando ou autorizada); depois de erro ou cancelamento pode emitir outra.
        DB::statement("CREATE UNIQUE INDEX notas_fiscais_conta_viva_unique ON notas_fiscais (account_receivable_id) WHERE status IN ('rascunho', 'processando', 'autorizada') AND account_receivable_id IS NOT NULL");

        Schema::create('tenant_fiscal_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('event_id', 120);
            $table->timestamps();
            $table->unique(['tenant_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_fiscal_events');
        Schema::dropIfExists('notas_fiscais');
        Schema::dropIfExists('tenant_fiscal_settings');
    }
};
