<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Separado de asaas_status de propósito -- asaas_status é sobre
            // SINCRONIZAÇÃO de cadastro (pending/synced/error, ver migration
            // 2026_05_21_115957), não sobre status de PAGAMENTO da
            // assinatura em si. asaas_last_payment_id guarda o id da última
            // cobrança processada pelo webhook, útil pra depuração/idempotência
            // manual sem precisar consultar a API do Asaas de novo.
            $table->enum('asaas_payment_status', ['em_dia', 'atrasado', 'cancelado'])
                ->default('em_dia')
                ->after('asaas_synced_at');
            $table->string('asaas_last_payment_id')->nullable()->after('asaas_payment_status');
            $table->timestamp('asaas_payment_updated_at')->nullable()->after('asaas_last_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['asaas_payment_status', 'asaas_last_payment_id', 'asaas_payment_updated_at']);
        });
    }
};
