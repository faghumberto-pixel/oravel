<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** G1 fase 5 -- liberacao excepcional de ativo Inapto e vinculo cumprimento -> OS automatica. 100% ADITIVA. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aptidao_liberacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->uuid('usuario_id')->nullable();
            $table->text('justificativa');
            $table->timestamp('valida_ate');
            $table->jsonb('motivos_snapshot')->nullable();   // o que estava impedindo, na epoca (historico imutavel)
            $table->timestamp('revogada_em')->nullable();
            $table->uuid('revogada_por')->nullable();
            $table->text('revogada_justificativa')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'valida_ate']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->timestamp('aptidao_liberada_ate')->nullable();
        });

        Schema::table('catalogo_cumprimentos', function (Blueprint $table) {
            $table->foreignUuid('maintenance_order_id')->nullable()->constrained('maintenance_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_cumprimentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_order_id');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('aptidao_liberada_ate');
        });

        Schema::dropIfExists('aptidao_liberacoes');
    }
};
