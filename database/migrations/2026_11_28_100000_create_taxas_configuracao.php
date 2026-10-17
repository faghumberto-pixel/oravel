<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Taxas de configuração opcionais, de pagamento único:
     *  - taxas_configuracao: catálogo global, valores editáveis na Central;
     *  - tenant_taxas: o que cada assinante aprovou (nome e valor congelados no momento da aprovação).
     */
    public function up(): void
    {
        Schema::create('taxas_configuracao', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('codigo', 40)->unique();
            $table->string('nome', 120);
            $table->text('descricao')->nullable();
            $table->decimal('valor', 10, 2)->nullable();
            $table->boolean('ativa')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->string('complemento_feature', 80)->nullable(); // recurso que depende de um complemento (aviso, não bloqueia)
            $table->timestamps();
        });

        Schema::create('tenant_taxas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('taxa_codigo', 40);
            $table->string('nome', 120);
            $table->decimal('valor', 10, 2);
            $table->string('origem', 12)->default('checkout');  // checkout | central
            $table->timestamp('aprovada_em')->nullable();
            $table->string('aprovada_ip', 45)->nullable();
            $table->string('status', 12)->default('aprovada');  // aprovada | cobrada | paga | cancelada
            $table->string('asaas_payment_id', 60)->nullable()->index();
            $table->text('invoice_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });

        // Uma taxa vigente (não cancelada) por assinante e código.
        DB::statement("CREATE UNIQUE INDEX tenant_taxas_vigente_unique ON tenant_taxas (tenant_id, taxa_codigo) WHERE status <> 'cancelada'");

        $agora = now();
        $catalogo = [
            ['nfe_nfse', 'Configuração de NF-e e NFS-e', 'Cadastro fiscal da empresa, certificado digital e primeira emissão de teste.', 98, true, 10, 'modulo_fiscal_nfse'],
            ['email', 'Configuração de e-mail', 'Conta de e-mail da empresa conectada ao sistema, com teste de envio e recebimento.', 70, true, 20, null],
            ['whatsapp', 'Configuração de WhatsApp', 'Conexão do número da empresa, modelos de mensagem e avisos automáticos.', 89, true, 30, null],
            ['cobranca_automatica', 'Configuração de cobrança automática', 'Conta Asaas da empresa, webhook, planos de cobrança e primeiro teste.', 115, true, 40, 'modulo_cobranca_automatica'],
            ['migracao_dados', 'Migração de dados', 'Importação dos seus cadastros e históricos de planilhas ou de outro sistema.', null, false, 50, null],
        ];

        foreach ($catalogo as [$codigo, $nome, $descricao, $valor, $ativa, $ordem, $feature]) {
            DB::table('taxas_configuracao')->insertOrIgnore([
                'id' => (string) Str::uuid(), 'codigo' => $codigo, 'nome' => $nome, 'descricao' => $descricao, 'valor' => $valor,
                'ativa' => $ativa, 'ordem' => $ordem, 'complemento_feature' => $feature, 'created_at' => $agora, 'updated_at' => $agora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_taxas');
        Schema::dropIfExists('taxas_configuracao');
    }
};
