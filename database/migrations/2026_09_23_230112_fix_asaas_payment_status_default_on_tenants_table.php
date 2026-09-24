<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug real achado em PROD 2026-09-23: a migration original
 * (2026_08_13_165612_add_payment_status_to_tenants_table) deu
 * default('em_dia') NOT NULL pra essa coluna -- todo Tenant novo nasce
 * marcado como "pagamento em dia" mesmo ANTES de qualquer webhook de
 * verdade confirmar isso. Contradiz a própria semântica documentada em
 * Tenant::isAccessBlockedForNonPayment() ("tenant sem status definido
 * nunca é sincronizado com a Asaas, também não é bloqueado -- ausência de
 * status não é o mesmo que inadimplência") -- o código já tratava NULL
 * como "nunca sincronizado" corretamente, só a coluna nunca permitia NULL
 * de fato.
 *
 * Na prática isso mascarou (não causou) um caso real de investigação: um
 * tenant que nunca completou o pagamento aparecia com
 * asaas_payment_status='em_dia' no banco, parecendo que o webhook tinha
 * confirmado o pagamento quando na verdade nunca chegou nenhum evento
 * (confirmado consultando a API da Asaas diretamente -- zero pagamentos
 * associados ao checkout). is_approved continuava false corretamente (o
 * bloqueio real funcionou), mas o dado enganoso quase levou a "corrigir"
 * uma aprovação que não deveria acontecer ainda.
 *
 * Tenants já existentes com 'em_dia' são deixados como estão (não dá pra
 * saber retroativamente quais eram confirmação real vs. default nunca
 * tocado) -- só tenants NOVOS a partir de agora nascem com NULL de
 * verdade, refletindo "nunca sincronizado".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('asaas_payment_status')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('asaas_payment_status', ['em_dia', 'atrasado', 'cancelado'])
                ->default('em_dia')
                ->change();
        });
    }
};
