<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Exigido pela Asaas pra criar o Checkout de pagamento -- achado real em
 * PROD 2026-09-23 ("O campo phoneNumber deve ser informado" e outros,
 * depois de corrigir o bug PIX/RECURRENT que mascarava esse erro). Sem
 * telefone nenhum coletado em lugar algum do sistema até então (nem
 * tenants, nem users).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('telefone')->nullable()->after('cpf_cnpj');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('telefone');
        });
    }
};
