<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sinistros e ocorrências da frota (colisão, avaria, furto/roubo...): B.O., seguradora, orçamento e dias parado. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_sinistros', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('motorista_id')->nullable()->constrained('fleet_drivers')->nullOnDelete();
            $table->foreignUuid('saida_veiculo_id')->nullable()->constrained('frota_saidas_veiculo')->nullOnDelete();
            $table->foreignUuid('ordem_servico_id')->nullable()->constrained('maintenance_orders')->nullOnDelete();
            $table->string('tipo', 20);                                // colisao | avaria | furto_roubo | incendio | alagamento | outro
            $table->timestamp('ocorrido_em');
            $table->string('local')->nullable();
            $table->text('descricao');
            $table->string('culpa', 15)->default('indefinida');        // propria | terceiro | indefinida
            $table->boolean('houve_vitima')->default(false);
            $table->string('bo_numero', 60)->nullable();
            $table->string('seguradora')->nullable();
            $table->string('apolice', 60)->nullable();
            $table->string('numero_sinistro_seguradora', 60)->nullable();
            $table->decimal('valor_orcamento', 12, 2)->nullable();
            $table->decimal('valor_franquia', 12, 2)->nullable();
            $table->boolean('veiculo_parado')->default(false);
            $table->timestamp('parado_desde')->nullable();
            $table->timestamp('voltou_a_rodar_em')->nullable();
            $table->string('situacao', 20)->default('aberto');         // aberto | em_orcamento | em_reparo | encerrado | cancelado
            $table->timestamp('encerrado_em')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'situacao', 'ocorrido_em']);
            $table->index(['tenant_id', 'ativo_id', 'veiculo_parado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_sinistros');
    }
};
