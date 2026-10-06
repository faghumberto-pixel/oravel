<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_pneus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('numero_fogo');                            // identificacao do pneu (marcada a fogo)
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('medida')->nullable();                     // ex.: 295/80 R22.5
            $table->string('dot', 4)->nullable();                     // semana + ano (ex.: 3524)
            $table->string('vida', 20)->default('novo');              // novo | recapado_1 | recapado_2 | recapado_3
            $table->decimal('sulco_inicial_mm', 5, 1)->nullable();
            $table->decimal('custo', 12, 2)->nullable();
            $table->decimal('pressao_min_psi', 5, 1)->nullable();     // faixa recomendada (alerta de pressao)
            $table->decimal('pressao_max_psi', 5, 1)->nullable();
            $table->string('situacao', 20)->default('estoque');       // estoque | montado | em_recapagem | sucateado
            $table->timestamps();
            $table->unique(['tenant_id', 'numero_fogo']);
            $table->index(['tenant_id', 'situacao']);
        });

        // GENERICA: serve a pneu e (Fase 3) a bateria. componente_type usa o mapa 'pneu' | 'bateria'.
        Schema::create('frota_instalacoes_componente', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->uuidMorphs('componente');
            $table->string('posicao', 20)->nullable();               // ex.: E1-LE, E2-LDE, ESTEPE
            $table->timestamp('instalado_em');
            $table->unsignedInteger('odometro_instalacao');
            $table->timestamp('removido_em')->nullable();
            $table->unsignedInteger('odometro_remocao')->nullable();
            $table->string('motivo_remocao', 20)->nullable();        // rodizio|desgaste|furo|recapagem|descarte|garantia|outro
            $table->foreignUuid('criado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'removido_em']);
        });

        // Regras no proprio banco: um componente so tem UMA instalacao aberta; uma posicao do veiculo so tem
        // UM componente aberto (por tipo).
        DB::statement('CREATE UNIQUE INDEX frota_instalacao_componente_aberta_unica ON frota_instalacoes_componente (componente_type, componente_id) WHERE removido_em IS NULL');
        DB::statement('CREATE UNIQUE INDEX frota_instalacao_posicao_aberta_unica ON frota_instalacoes_componente (ativo_id, componente_type, posicao) WHERE removido_em IS NULL AND posicao IS NOT NULL');

        // Inspecao: sem tenant_id (isolamento pelo pneu / pelo veiculo). pneu_id nulo = menor sulco do VEICULO
        // medido no checklist completo.
        Schema::create('frota_inspecoes_pneu', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pneu_id')->nullable()->constrained('frota_pneus')->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->nullable()->constrained('assets')->cascadeOnDelete();   // nulo = pneu medido em estoque
            $table->foreignUuid('checklist_id')->nullable()->constrained('frota_checklists')->nullOnDelete();
            $table->decimal('sulco_mm', 5, 1)->nullable();
            $table->decimal('pressao_psi', 5, 1)->nullable();
            $table->unsignedInteger('odometro')->nullable();
            $table->timestamp('inspecionado_em');
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->index(['ativo_id', 'inspecionado_em']);
            $table->index(['pneu_id', 'inspecionado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_inspecoes_pneu');
        Schema::dropIfExists('frota_instalacoes_componente');
        Schema::dropIfExists('frota_pneus');
    }
};
