<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_receivables', function (Blueprint $table) {
            $table->string('asaas_payment_id')->nullable()->unique()->after('quote_id');
            $table->string('asaas_invoice_url')->nullable()->after('asaas_payment_id');
            $table->string('asaas_boleto_url')->nullable()->after('asaas_invoice_url');
        });
    }

    public function down(): void
    {
        Schema::table('account_receivables', function (Blueprint $table) {
            $table->dropColumn(['asaas_payment_id', 'asaas_invoice_url', 'asaas_boleto_url']);
        });
    }
};
