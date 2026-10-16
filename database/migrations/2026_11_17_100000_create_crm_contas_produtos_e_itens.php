<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM com vida própria (Fase 6A): cadastro simples de Contas (clientes do CRM, sem depender de `clients`
     * do ERP), seus contatos, catálogo de produtos/serviços e itens de interesse do lead.
     */
    public function up(): void
    {
        Schema::create('crm_contas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nome');
            $table->string('nome_fantasia')->nullable();
            $table->string('tipo_pessoa', 2)->default('pj'); // pj | pf
            $table->string('documento', 20)->nullable();
            $table->string('documento_digitos', 14)->nullable(); // só dígitos: base da unicidade por tenant
            $table->string('email')->nullable();
            $table->string('telefone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('segmento')->nullable();
            $table->string('cep', 12)->nullable();
            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->foreignUuid('responsavel_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origem')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativa')->default(true);
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete(); // vínculo opcional com o ERP
            $table->timestamps();

            $table->index(['tenant_id', 'nome']);
        });

        DB::statement("ALTER TABLE crm_contas ADD COLUMN custom jsonb NOT NULL DEFAULT '{}'::jsonb");
        DB::statement('CREATE UNIQUE INDEX crm_contas_documento_unico ON crm_contas (tenant_id, documento_digitos) WHERE documento_digitos IS NOT NULL');

        Schema::create('crm_conta_contatos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('crm_conta_id')->constrained('crm_contas')->cascadeOnDelete();
            $table->string('name');
            $table->string('role_title')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'crm_conta_id']);
        });

        Schema::create('crm_produtos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('codigo', 40)->nullable();
            $table->string('nome');
            $table->string('tipo', 10)->default('servico'); // produto | servico
            $table->string('unidade', 20)->default('un');
            $table->decimal('preco', 15, 2)->default(0);
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'nome']);
        });

        DB::statement('CREATE UNIQUE INDEX crm_produtos_codigo_unico ON crm_produtos (tenant_id, codigo) WHERE codigo IS NOT NULL');

        Schema::create('crm_lead_itens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('crm_lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignUuid('crm_produto_id')->nullable()->constrained('crm_produtos')->nullOnDelete();
            $table->string('descricao')->nullable(); // item livre quando não vem do catálogo
            $table->decimal('quantidade', 12, 2)->default(1);
            $table->decimal('preco_unitario', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'crm_lead_id']);
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->foreignUuid('crm_conta_id')->nullable()->after('client_id')->constrained('crm_contas')->nullOnDelete();
        });

        Schema::table('whatsapp_conversas', function (Blueprint $table) {
            $table->foreignUuid('crm_conta_id')->nullable()->after('client_id')->constrained('crm_contas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_conversas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crm_conta_id');
        });

        Schema::table('crm_leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('crm_conta_id');
        });

        Schema::dropIfExists('crm_lead_itens');
        DB::statement('DROP INDEX IF EXISTS crm_produtos_codigo_unico');
        Schema::dropIfExists('crm_produtos');
        Schema::dropIfExists('crm_conta_contatos');
        DB::statement('DROP INDEX IF EXISTS crm_contas_documento_unico');
        Schema::dropIfExists('crm_contas');
    }
};
