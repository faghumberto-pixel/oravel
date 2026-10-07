<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Motorista titular de cada veículo (com histórico) e controle de chaves (entrega e devolução com responsável). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_vinculos_motorista', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('motorista_id')->constrained('fleet_drivers')->cascadeOnDelete();
            $table->date('inicio');
            $table->date('fim')->nullable();                           // em branco = titular atual
            $table->text('observacoes')->nullable();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'motorista_id', 'fim']);
        });
        // Um veículo só tem um titular vigente por vez.
        DB::statement('CREATE UNIQUE INDEX frota_vinculo_titular_vigente_unico ON frota_vinculos_motorista (ativo_id) WHERE fim IS NULL');

        Schema::create('frota_chaves', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->string('identificacao');                           // ex.: Chave principal, Reserva, Controle do portão
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'ativo']);
        });
        DB::statement('CREATE UNIQUE INDEX frota_chave_identificacao_unica ON frota_chaves (ativo_id, lower(identificacao)) WHERE ativo = true');

        Schema::create('frota_entregas_chave', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('chave_id')->constrained('frota_chaves')->cascadeOnDelete();
            $table->foreignUuid('motorista_id')->nullable()->constrained('fleet_drivers')->nullOnDelete();
            $table->string('responsavel_nome')->nullable();            // quando quem recebe não é motorista cadastrado (ex.: mecânico)
            $table->timestamp('entregue_em');
            $table->string('motivo');
            $table->text('observacoes_entrega')->nullable();
            $table->timestamp('devolvida_em')->nullable();
            $table->text('observacoes_devolucao')->nullable();
            $table->foreignUuid('entregue_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('devolucao_registrada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'chave_id', 'entregue_em']);
        });
        // Uma chave só está com uma pessoa por vez.
        DB::statement('CREATE UNIQUE INDEX frota_entrega_chave_aberta_unica ON frota_entregas_chave (chave_id) WHERE devolvida_em IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_entregas_chave');
        Schema::dropIfExists('frota_chaves');
        Schema::dropIfExists('frota_vinculos_motorista');
    }
};
