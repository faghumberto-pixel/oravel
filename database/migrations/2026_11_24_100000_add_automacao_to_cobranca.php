<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cobrança automática (8B): chaves de automação por empresa e controle de tentativas por conta a receber. */
    public function up(): void
    {
        Schema::table('tenant_asaas_settings', function (Blueprint $table) {
            $table->boolean('gerar_recorrentes')->default(false)->after('juros_mensal');   // cria as contas dos Planos de Cobrança
            $table->boolean('cobrar_automaticamente')->default(false)->after('gerar_recorrentes'); // gera a cobrança no Asaas das contas novas
            $table->unsignedSmallInteger('dias_antecedencia')->default(10)->after('cobrar_automaticamente');
        });

        Schema::table('account_receivables', function (Blueprint $table) {
            $table->unsignedSmallInteger('cobranca_tentativas')->default(0)->after('asaas_status');
            $table->text('cobranca_erro')->nullable()->after('cobranca_tentativas');
            $table->timestamp('cobranca_ultima_tentativa_em')->nullable()->after('cobranca_erro');
        });
    }

    public function down(): void
    {
        Schema::table('account_receivables', fn (Blueprint $t) => $t->dropColumn(['cobranca_tentativas', 'cobranca_erro', 'cobranca_ultima_tentativa_em']));
        Schema::table('tenant_asaas_settings', fn (Blueprint $t) => $t->dropColumn(['gerar_recorrentes', 'cobrar_automaticamente', 'dias_antecedencia']));
    }
};
