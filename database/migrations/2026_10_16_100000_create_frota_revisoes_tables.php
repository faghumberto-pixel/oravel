<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Revisões preventivas do veículo por km e/ou tempo (freios, correia, filtros...), com o histórico do que foi feito. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_planos_revisao', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->string('nome');                                    // ex.: Freios, Correia, Filtro de ar
            $table->unsignedInteger('intervalo_km')->nullable();
            $table->unsignedInteger('intervalo_dias')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'ativo']);
        });
        DB::statement('CREATE UNIQUE INDEX frota_plano_revisao_nome_unico ON frota_planos_revisao (ativo_id, lower(nome)) WHERE ativo = true');

        Schema::create('frota_revisoes_realizadas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('plano_id')->constrained('frota_planos_revisao')->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->date('realizada_em');
            $table->unsignedInteger('odometro');
            $table->decimal('custo', 12, 2)->nullable();
            $table->foreignUuid('ordem_servico_id')->nullable()->constrained('maintenance_orders')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->foreignUuid('realizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'plano_id', 'realizada_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_revisoes_realizadas');
        Schema::dropIfExists('frota_planos_revisao');
    }
};
