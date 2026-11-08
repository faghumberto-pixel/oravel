<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C4: marca o que foi copiado do modulo Frota (checklists de saida/retorno em PROD) para o motor novo, para a copia ser idempotente e
 * o historico/relatorio ficar unificado sem apagar nada da Frota. Aditiva: so colunas nulas.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['ck_templates', 'ck_executions'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                $table->string('legado_tipo', 30)->nullable();   // frota_modelo | frota_checklist
                $table->uuid('legado_id')->nullable();
                $table->unique(['tenant_id', 'legado_tipo', 'legado_id'], $tabela.'_legado_unique');
            });
        }
    }

    public function down(): void
    {
        foreach (['ck_templates', 'ck_executions'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) use ($tabela) {
                $table->dropUnique($tabela.'_legado_unique');
                $table->dropColumn(['legado_tipo', 'legado_id']);
            });
        }
    }
};
