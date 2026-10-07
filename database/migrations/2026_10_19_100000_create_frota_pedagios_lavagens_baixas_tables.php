<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Pedágio e tag, lavagem e baixa/venda do veículo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->string('numero', 60);
            $table->string('operadora', 20);                           // sem_parar | conectcar | veloe | move_mais | outra
            $table->text('observacoes')->nullable();
            $table->boolean('ativa')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'numero']);
        });
        // Um veículo tem uma tag ativa por vez.
        DB::statement('CREATE UNIQUE INDEX frota_tag_ativa_unica ON frota_tags (ativo_id) WHERE ativa = true');

        Schema::create('frota_pedagios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->nullable()->constrained('frota_tags')->nullOnDelete();
            $table->foreignUuid('motorista_id')->nullable()->constrained('fleet_drivers')->nullOnDelete();
            $table->foreignUuid('saida_veiculo_id')->nullable()->constrained('frota_saidas_veiculo')->nullOnDelete();
            $table->timestamp('passou_em');
            $table->string('local');
            $table->decimal('valor', 10, 2);
            $table->text('observacoes')->nullable();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'passou_em']);
        });

        Schema::create('frota_lavagens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->date('realizada_em');
            $table->string('tipo', 20);                                // simples | completa | higienizacao | motor | outro
            $table->decimal('valor', 10, 2)->nullable();
            $table->string('local')->nullable();
            $table->unsignedSmallInteger('intervalo_dias')->nullable(); // repetir a cada N dias (agenda)
            $table->date('proxima_prevista')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'realizada_em']);
        });

        Schema::create('frota_baixas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->string('tipo', 20);                                // venda | sucata | perda_total | doacao | outro
            $table->date('data');
            $table->decimal('valor', 12, 2)->nullable();               // valor da venda
            $table->string('comprador')->nullable();
            $table->string('documento', 60)->nullable();               // nota fiscal / recibo / CRV
            $table->unsignedInteger('odometro_final')->nullable();
            $table->text('motivo');
            $table->timestamp('revertida_em')->nullable();
            $table->text('motivo_reversao')->nullable();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id']);
        });
        // Um veículo só tem uma baixa vigente.
        DB::statement('CREATE UNIQUE INDEX frota_baixa_vigente_unica ON frota_baixas (ativo_id) WHERE revertida_em IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_baixas');
        Schema::dropIfExists('frota_lavagens');
        Schema::dropIfExists('frota_pedagios');
        Schema::dropIfExists('frota_tags');
    }
};
