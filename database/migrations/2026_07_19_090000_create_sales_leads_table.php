<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM comercial da propria Oravel (prospeccao de novos tenants), painel
     * Central -- NAO usa tenant_id/BelongsToTenant, mesmo raciocinio de
     * announcements/plans: nao e dado de um tenant, e recurso da operacao
     * SaaS. Nao confundir com crm_leads (esse sim por tenant, pra cada
     * locadora vender pros clientes dela).
     */
    public function up(): void
    {
        Schema::create('sales_leads', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('company_name');
            $table->string('website')->nullable();
            $table->string('decision_maker_name')->nullable();
            $table->string('decision_maker_role')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('source')->nullable();

            // Mesmos valores de tenants.segment/Client::NICHE_* de proposito
            // -- vocabulario unico, nao paralelo. Quando o lead converte,
            // o Tenant nasce com esse segmento ja certo.
            $table->string('segment')->nullable();

            $table->decimal('estimated_contract_value', 15, 2)->nullable();
            $table->text('critical_pain')->nullable();

            $table->string('pipeline_stage')->default('prospeccao');
            $table->string('lost_reason')->nullable();
            $table->text('lost_reason_detail')->nullable();

            $table->foreignUuid('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('converted_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('cep')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->timestamp('last_interaction_at')->nullable();
            $table->date('next_followup_date')->nullable();

            $table->timestamps();

            $table->index('pipeline_stage');
            $table->index('assigned_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_leads');
    }
};
