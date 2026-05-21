<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Guarda o ID único do cliente lá dentro do Asaas (ex: cus_0000012345)
            $table->string('asaas_customer_id')->nullable()->after('plan_id')->index();
            
            // Controla o status da assinatura deste cliente no SaaS
            $table->string('subscription_status')->default('trial')->after('asaas_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['asaas_customer_id', 'subscription_status']);
        });
    }
};