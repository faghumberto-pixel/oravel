<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cobrança automática (complemento): conta Asaas da PRÓPRIA empresa cliente. A chave fica criptografada (cast no model)
     * e o webhook é por empresa, com token próprio. clients.asaas_customer_id guarda o cliente na conta Asaas da empresa.
     */
    public function up(): void
    {
        Schema::create('tenant_asaas_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->string('ambiente', 10)->default('sandbox'); // sandbox | producao
            $table->text('api_key')->nullable();                // criptografada
            $table->string('webhook_token', 64);
            $table->string('forma_padrao', 20)->default('UNDEFINED'); // UNDEFINED = o cliente escolhe boleto, Pix ou cartão
            $table->decimal('multa_percentual', 5, 2)->nullable();
            $table->decimal('juros_mensal', 5, 2)->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        // Eventos do webhook já tratados (o Asaas reenvia em caso de falha): cada evento vale uma vez por empresa.
        Schema::create('tenant_asaas_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('event_id', 80);
            $table->string('event', 60)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'event_id']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('asaas_customer_id', 60)->nullable()->after('cpf_cnpj');
        });

        Schema::table('account_receivables', function (Blueprint $table) {
            $table->string('asaas_status', 40)->nullable()->after('asaas_boleto_url');
        });
    }

    public function down(): void
    {
        Schema::table('account_receivables', fn (Blueprint $t) => $t->dropColumn('asaas_status'));
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('asaas_customer_id'));
        Schema::dropIfExists('tenant_asaas_events');
        Schema::dropIfExists('tenant_asaas_settings');
    }
};
