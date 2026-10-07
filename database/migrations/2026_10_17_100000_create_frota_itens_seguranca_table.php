<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Kit de segurança do veículo (extintor, triângulo, macaco...): o que deve ter, se está presente, validade e última conferência. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_itens_seguranca', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->string('nome');                                    // ex.: Extintor ABC, Triângulo
            $table->string('identificacao', 60)->nullable();           // série/lacre (extintor)
            $table->boolean('obrigatorio')->default(true);
            $table->boolean('tem_validade')->default(false);
            $table->date('validade')->nullable();
            $table->boolean('presente')->default(true);
            $table->date('conferido_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'ativo']);
        });
        DB::statement('CREATE UNIQUE INDEX frota_item_seguranca_nome_unico ON frota_itens_seguranca (ativo_id, lower(nome)) WHERE ativo = true');
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_itens_seguranca');
    }
};
