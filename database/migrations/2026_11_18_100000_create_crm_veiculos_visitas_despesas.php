<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** CRM de campo (Fase 6B): veículos dos vendedores, visitas e despesas de visita. */
    public function up(): void
    {
        Schema::create('crm_veiculos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('placa', 10);
            $table->string('descricao')->nullable();
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->unsignedSmallInteger('ano')->nullable();
            $table->unsignedInteger('km_atual')->default(0);
            $table->foreignUuid('responsavel_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('vencimento_licenciamento')->nullable();
            $table->date('vencimento_seguro')->nullable();
            $table->unsignedInteger('proxima_revisao_km')->nullable();
            $table->boolean('ativo')->default(true);
            $table->text('observacoes')->nullable();
            $table->foreignUuid('fleet_vehicle_id')->nullable()->constrained('fleet_vehicles')->nullOnDelete(); // vínculo opcional com a Frota do ERP
            $table->timestamps();

            $table->unique(['tenant_id', 'placa']);
        });

        Schema::create('crm_visitas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('crm_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignUuid('crm_conta_id')->nullable()->constrained('crm_contas')->nullOnDelete();
            $table->string('contato_nome')->nullable();
            $table->string('objetivo', 30)->default('apresentacao');
            $table->dateTime('agendada_para')->nullable();
            $table->unsignedSmallInteger('duracao_prevista_min')->default(60);
            $table->string('status', 20)->default('agendada');
            $table->string('endereco')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->foreignUuid('crm_veiculo_id')->nullable()->constrained('crm_veiculos')->nullOnDelete();
            $table->unsignedInteger('km_inicio')->nullable();
            $table->unsignedInteger('km_fim')->nullable();
            $table->dateTime('checkin_em')->nullable();
            $table->decimal('checkin_lat', 10, 8)->nullable();
            $table->decimal('checkin_lng', 11, 8)->nullable();
            $table->unsignedInteger('checkin_distancia_m')->nullable();
            $table->dateTime('checkout_em')->nullable();
            $table->decimal('checkout_lat', 10, 8)->nullable();
            $table->decimal('checkout_lng', 11, 8)->nullable();
            $table->text('resultado')->nullable();
            $table->text('proximo_passo')->nullable();
            $table->date('proximo_contato')->nullable();
            $table->unsignedSmallInteger('ordem_roteiro')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'agendada_para']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('crm_visita_despesas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('crm_visita_id')->nullable()->constrained('crm_visitas')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('crm_veiculo_id')->nullable()->constrained('crm_veiculos')->nullOnDelete();
            $table->date('data');
            $table->string('tipo', 20)->default('combustivel');
            $table->decimal('valor', 12, 2);
            $table->string('descricao')->nullable();
            $table->decimal('litros', 8, 2)->nullable();
            $table->string('status', 15)->default('pendente');
            $table->foreignUuid('aprovado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('aprovado_em')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'data']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_visita_despesas');
        Schema::dropIfExists('crm_visitas');
        Schema::dropIfExists('crm_veiculos');
    }
};
