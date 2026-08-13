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
            // Exigido pela Asaas pra criar um customer (AsaasService::
            // createCustomer()) -- sem CPF/CNPJ a chamada é rejeitada pela
            // API deles. Nullable porque tenants já existentes não têm
            // esse dado ainda (preenchido manualmente depois, ou só
            // passa a ser obrigatório em novos cadastros via form).
            $table->string('cpf_cnpj')->nullable()->after('mrr_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('cpf_cnpj');
        });
    }
};
