<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Campos base de localização e identificação (caso não existam no banco original)
            if (!Schema::hasColumn('clients', 'document')) $table->string('document')->nullable();
            if (!Schema::hasColumn('clients', 'address')) $table->string('address')->nullable();
            if (!Schema::hasColumn('clients', 'city')) $table->string('city')->nullable();
            if (!Schema::hasColumn('clients', 'state')) $table->string('state', 2)->nullable();

            // Dados de Identificação PJ
            if (!Schema::hasColumn('clients', 'fantasy_name')) $table->string('fantasy_name')->nullable();
            if (!Schema::hasColumn('clients', 'state_registration')) $table->string('state_registration')->nullable();
            if (!Schema::hasColumn('clients', 'municipal_registration')) $table->string('municipal_registration')->nullable();
            if (!Schema::hasColumn('clients', 'tax_regime')) $table->string('tax_regime')->nullable();

            // Endereço de Faturamento adicional
            if (!Schema::hasColumn('clients', 'address_complement')) $table->string('address_complement')->nullable();
            if (!Schema::hasColumn('clients', 'neighborhood')) $table->string('neighborhood')->nullable();
            if (!Schema::hasColumn('clients', 'zip_code')) $table->string('zip_code')->nullable();

            // Local de Entrega
            if (!Schema::hasColumn('clients', 'delivery_address')) $table->string('delivery_address')->nullable();
            if (!Schema::hasColumn('clients', 'site_manager')) $table->string('site_manager')->nullable();
            if (!Schema::hasColumn('clients', 'site_phone')) $table->string('site_phone')->nullable();

            // Contatos e Setores ERP
            if (!Schema::hasColumn('clients', 'whatsapp')) $table->string('whatsapp')->nullable();
            if (!Schema::hasColumn('clients', 'email_financial')) $table->string('email_financial')->nullable();
            if (!Schema::hasColumn('clients', 'email_purchasing')) $table->string('email_purchasing')->nullable();

            // Representante Legal
            if (!Schema::hasColumn('clients', 'legal_name')) $table->string('legal_name')->nullable();
            if (!Schema::hasColumn('clients', 'legal_cpf')) $table->string('legal_cpf')->nullable();
            if (!Schema::hasColumn('clients', 'legal_rg')) $table->string('legal_rg')->nullable();
            if (!Schema::hasColumn('clients', 'legal_role')) $table->string('legal_role')->nullable();

            // Checklist de Documentos (Booleanos)
            if (!Schema::hasColumn('clients', 'doc_cnpj')) $table->boolean('doc_cnpj')->default(false);
            if (!Schema::hasColumn('clients', 'doc_statute')) $table->boolean('doc_statute')->default(false);
            if (!Schema::hasColumn('clients', 'doc_id')) $table->boolean('doc_id')->default(false);
            if (!Schema::hasColumn('clients', 'doc_proxy')) $table->boolean('doc_proxy')->default(false);
            if (!Schema::hasColumn('clients', 'doc_address')) $table->boolean('doc_address')->default(false);
            if (!Schema::hasColumn('clients', 'doc_art')) $table->boolean('doc_art')->default(false);
            if (!Schema::hasColumn('clients', 'doc_registration_form')) $table->boolean('doc_registration_form')->default(false);

            // Processo de Análise de Risco
            if (!Schema::hasColumn('clients', 'check_internal_fraud')) $table->boolean('check_internal_fraud')->default(false);
            if (!Schema::hasColumn('clients', 'check_blacklist')) $table->boolean('check_blacklist')->default(false);
            if (!Schema::hasColumn('clients', 'check_credit_bureau')) $table->boolean('check_credit_bureau')->default(false);
            if (!Schema::hasColumn('clients', 'credit_score')) $table->integer('credit_score')->nullable();
            if (!Schema::hasColumn('clients', 'check_query_history')) $table->boolean('check_query_history')->default(false);
            if (!Schema::hasColumn('clients', 'check_sinintegra')) $table->boolean('check_sinintegra')->default(false);
            if (!Schema::hasColumn('clients', 'commercial_references')) $table->text('commercial_references')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $columnsToDrop = [
                'document', 'address', 'city', 'state', 'fantasy_name', 'state_registration', 
                'municipal_registration', 'tax_regime', 'address_complement', 'neighborhood', 
                'zip_code', 'delivery_address', 'site_manager', 'site_phone', 'whatsapp', 
                'email_financial', 'email_purchasing', 'legal_name', 'legal_cpf', 'legal_rg', 'legal_role',
                'doc_cnpj', 'doc_statute', 'doc_id', 'doc_proxy', 'doc_address', 'doc_art', 'doc_registration_form',
                'check_internal_fraud', 'check_blacklist', 'check_credit_bureau', 'credit_score', 'check_query_history', 'check_sinintegra', 'commercial_references'
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};