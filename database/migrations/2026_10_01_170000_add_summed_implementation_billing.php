<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Como a implantação é cobrada: 'separada' (cobranças avulsas) ou
        // 'somada' (parcelas somadas às primeiras mensalidades da assinatura).
        Schema::table('plans', function (Blueprint $table) {
            $table->string('implementation_billing_mode', 20)->default('separada');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('implementation_billing_mode', 20)->nullable();
            // Preenchido quando a assinatura volta ao valor normal depois da última parcela somada.
            $table->timestamp('subscription_reverted_to_base_at')->nullable();
        });

        Schema::table('implementation_charges', function (Blueprint $table) {
            // true = parcela somada à mensalidade (sem cobrança avulsa própria).
            $table->boolean('included_in_subscription')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('implementation_charges', function (Blueprint $table) {
            $table->dropColumn('included_in_subscription');
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['implementation_billing_mode', 'subscription_reverted_to_base_at']);
        });
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('implementation_billing_mode');
        });
    }
};
