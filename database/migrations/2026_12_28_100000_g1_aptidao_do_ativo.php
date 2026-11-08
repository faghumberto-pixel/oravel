<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G1 fase 3 -- aptidao do ativo. 100% ADITIVA: colunas anulaveis/com default em assets + tabela nova de cumprimentos.
 * O cache de aptidao so e preenchido para tenants com a chave tabela_catalogo_equipamentos ligada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('aptidao_status')->nullable();            // apto | apto_restricao | inapto | cadastro_incompleto
            $table->jsonb('aptidao_motivos')->nullable();
            $table->boolean('aptidao_bloqueia_alocacao')->default(false);
            $table->timestamp('aptidao_calculada_em')->nullable();
            $table->jsonb('atributos_tecnicos')->nullable();         // valores dos campos tecnicos do grupo (form na fase 4)
            $table->index(['tenant_id', 'aptidao_status']);
        });

        Schema::create('catalogo_cumprimentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('requisito_id')->nullable()->constrained('catalogo_requisitos')->nullOnDelete();
            $table->foreignUuid('preventiva_id')->nullable()->constrained('catalogo_preventivas')->nullOnDelete();
            $table->string('resultado')->default('ok'); // ok | reprovado
            $table->timestamp('realizado_em');
            $table->date('valido_ate')->nullable();
            $table->decimal('medidor_leitura', 12, 2)->nullable(); // horimetro/km no momento (preventiva)
            $table->jsonb('snapshot')->nullable();                 // regra exigida NA EPOCA: historico imutavel
            $table->string('origem')->nullable();
            $table->string('origem_id')->nullable();
            $table->text('observacao')->nullable();
            $table->uuid('registrado_por')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'requisito_id', 'realizado_em']);
            $table->index(['asset_id', 'preventiva_id', 'realizado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_cumprimentos');

        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'aptidao_status']);
            $table->dropColumn(['aptidao_status', 'aptidao_motivos', 'aptidao_bloqueia_alocacao', 'aptidao_calculada_em', 'atributos_tecnicos']);
        });
    }
};
