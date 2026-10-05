<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Quem do tenant recebe os avisos financeiros (contas a pagar/receber,
            // excedente de contrato). Se ninguém do tenant estiver marcado, vale a regra
            // antiga (User::podeReceberFinancas) -- ver User::financialNotificationRecipients().
            $table->boolean('receives_financial_notifications')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('receives_financial_notifications');
        });
    }
};
