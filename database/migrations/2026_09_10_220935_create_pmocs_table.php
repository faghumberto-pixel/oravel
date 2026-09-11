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
        Schema::create('pmocs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('local_ambiente');
            $table->string('tipo_sistema')->comment('Ex: Split, Central, Janela, etc');
            $table->foreignUuid('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('responsavel_nome');
            $table->string('responsavel_telefone')->nullable();
            $table->string('responsavel_email')->nullable();
            $table->date('data_inicio_vigencia');
            $table->date('data_fim_vigencia')->nullable();
            $table->integer('frequencia_dias')->comment('Frequência de manutenção em dias');
            $table->text('procedimentos_limpeza');
            $table->text('procedimentos_filtros')->comment('Especificar tipos de filtros e frequência');
            $table->text('procedimentos_inspecao');
            $table->text('procedimentos_medicao')->comment('Medições de ar, umidade, etc');
            $table->decimal('temperatura_ideal_min', 5, 2)->nullable()->comment('Temperatura mínima ideal');
            $table->decimal('temperatura_ideal_max', 5, 2)->nullable()->comment('Temperatura máxima ideal');
            $table->decimal('umidade_ideal_min', 5, 2)->nullable()->comment('Umidade relativa mínima');
            $table->decimal('umidade_ideal_max', 5, 2)->nullable()->comment('Umidade relativa máxima');
            $table->text('parametros_qualidade_ar')->nullable()->comment('Padrões de qualidade do ar a manter');
            $table->text('observacoes')->nullable();
            $table->enum('status', ['ativo', 'inativo', 'em_revisao'])->default('ativo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pmocs');
    }
};
