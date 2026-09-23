<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda o id do Checkout da Asaas (POST /v3/checkouts, cartão de
     * crédito/Pix) criado no cadastro do Tenant -- fluxo separado de
     * asaas_subscription_id (o antigo createSubscription() + fatura
     * genérica). Ver AsaasService::createTenantCheckout() e
     * AsaasCheckoutController::store().
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('asaas_checkout_id')->nullable()->after('asaas_subscription_id');
            $table->index('asaas_checkout_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['asaas_checkout_id']);
            $table->dropColumn('asaas_checkout_id');
        });
    }
};
