<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Entrada e saída de veículos da frota: quem levou, para onde, por quê, km e quando voltou. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_saidas_veiculo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('motorista_id')->nullable()->constrained('fleet_drivers')->nullOnDelete();
            $table->string('condutor_nome')->nullable();               // quando o condutor não é motorista cadastrado
            $table->string('finalidade', 30);                          // visita_tecnica | administrativo | diretoria | cliente | locacao | manutencao | outro
            $table->string('destino');
            $table->text('motivo');                                     // por que o veículo saiu (texto livre; a finalidade é a categoria)
            $table->foreignUuid('cliente_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignUuid('ordem_servico_id')->nullable()->constrained('maintenance_orders')->nullOnDelete();
            $table->timestamp('saida_em');
            $table->unsignedInteger('odometro_saida');
            $table->string('combustivel_saida', 20)->nullable();
            $table->foreignUuid('checklist_saida_id')->nullable()->constrained('frota_checklists')->nullOnDelete();
            $table->text('observacoes_saida')->nullable();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retorno_em')->nullable();
            $table->unsignedInteger('odometro_retorno')->nullable();
            $table->string('combustivel_retorno', 20)->nullable();
            $table->foreignUuid('checklist_retorno_id')->nullable()->constrained('frota_checklists')->nullOnDelete();
            $table->text('observacoes_retorno')->nullable();
            $table->foreignUuid('retorno_registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'saida_em']);
            $table->index(['tenant_id', 'retorno_em']);
        });

        // Um veículo só pode estar fora uma vez por vez (garantido também no banco).
        DB::statement('CREATE UNIQUE INDEX frota_saida_veiculo_aberta_unica ON frota_saidas_veiculo (ativo_id) WHERE retorno_em IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_saidas_veiculo');
    }
};
