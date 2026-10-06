<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Um checklist de SAIDA ou RETORNO de um veiculo (ativo do tipo veiculo).
        Schema::create('frota_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('motorista_id')->nullable()->constrained('fleet_drivers')->nullOnDelete();
            $table->foreignUuid('modelo_id')->nullable()->constrained('frota_modelos_checklist')->nullOnDelete();
            $table->foreignUuid('preenchido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 10);                              // saida | retorno
            $table->unsignedInteger('odometro');                     // km
            $table->string('nivel_combustivel', 20)->nullable();
            $table->string('situacao', 20)->default('ok');           // ok | atencao | bloqueado | liberado
            $table->foreignUuid('liberado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('liberado_em')->nullable();
            $table->text('motivo_liberacao')->nullable();
            $table->longText('assinatura')->nullable();              // imagem (data URL), mesmo padrao das assinaturas da OS
            $table->text('observacoes')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->uuid('checklist_par_id')->nullable();             // liga o retorno a saida (FK logo abaixo)
            $table->foreignUuid('movimentacao_equipamento_id')->nullable()->constrained('equipment_movements')->nullOnDelete();
            $table->foreignUuid('ordem_servico_id')->nullable()->constrained('maintenance_orders')->nullOnDelete(); // ponte opcional com a OS
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'concluido_em']);
            $table->index(['tenant_id', 'situacao']);
        });

        // Auto-referencia: so depois de a chave primaria existir.
        Schema::table('frota_checklists', function (Blueprint $table) {
            $table->foreign('checklist_par_id')->references('id')->on('frota_checklists')->nullOnDelete();
        });

        // Resposta: sem tenant_id (isolamento pelo checklist). Guarda COPIA do texto e da gravidade do item,
        // para o historico nao mudar se o modelo for editado depois.
        Schema::create('frota_respostas_checklist', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('checklist_id')->constrained('frota_checklists')->cascadeOnDelete();
            $table->foreignUuid('item_id')->nullable()->constrained('frota_itens_modelo_checklist')->nullOnDelete();
            $table->string('descricao_registrada');
            $table->string('categoria_registrada', 30)->nullable();
            $table->string('gravidade_registrada', 20);
            $table->string('resultado', 20);                          // ok | problema | nao_se_aplica
            $table->decimal('valor_numerico', 10, 2)->nullable();
            $table->string('unidade', 20)->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->index('checklist_id');
        });

        // Leituras do odometro: so se acrescenta (nunca edita nem apaga) -- mesmo padrao das leituras de horimetro.
        Schema::create('frota_leituras_odometro', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->unsignedInteger('odometro');
            $table->string('origem', 20)->default('manual');         // checklist | oleo | pneu | bateria | manual
            $table->uuid('origem_id')->nullable();
            $table->text('justificativa')->nullable();               // exigida quando a leitura e menor que a anterior
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lido_em');
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'lido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_leituras_odometro');
        Schema::dropIfExists('frota_respostas_checklist');
        Schema::dropIfExists('frota_checklists');
    }
};
