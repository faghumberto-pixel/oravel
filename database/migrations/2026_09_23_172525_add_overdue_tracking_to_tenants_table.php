<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suporte ao bloqueio real de acesso por inadimplência (pedido do
     * usuário 2026-09-23 -- "isso é exatamente o que precisa ser
     * corrigido": asaas_payment_status virava 'atrasado'/'cancelado' mas
     * nada travava o acesso de fato, era só informativo). Ver
     * Tenant::isAccessBlockedForNonPayment() e
     * App\Http\Middleware\EnsureTenantPaymentIsCurrent.
     *
     * asaas_overdue_since: DESDE QUANDO está atrasado -- campo separado de
     * asaas_payment_updated_at de propósito. Esse último é sobrescrito a
     * cada webhook processado (mesmo reenvios do MESMO evento PAYMENT_OVERDUE,
     * que a Asaas pode reenviar), então não serve pra contar prazo de
     * tolerância -- resetaria a cada reenvio e o cliente nunca seria
     * bloqueado. asaas_overdue_since só é setado na TRANSIÇÃO pra
     * 'atrasado' (nunca se já estava atrasado) e limpo quando volta pra
     * 'em_dia'.
     *
     * asaas_current_invoice_url: link da fatura em aberto, capturado
     * direto do payload do webhook (payment.invoiceUrl) -- pra tela de
     * bloqueio linkar direto pro pagamento, sem precisar de uma chamada
     * síncrona à API da Asaas quando o usuário bloqueado abre a tela.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('asaas_overdue_since')->nullable()->after('asaas_payment_updated_at');
            $table->string('asaas_current_invoice_url')->nullable()->after('asaas_overdue_since');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['asaas_overdue_since', 'asaas_current_invoice_url']);
        });
    }
};
