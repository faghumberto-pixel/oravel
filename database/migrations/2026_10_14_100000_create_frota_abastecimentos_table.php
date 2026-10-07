<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Abastecimentos da frota: litros, valor, odômetro e o consumo (km/l) calculado pelo método do tanque cheio. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frota_abastecimentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('ativo_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignUuid('motorista_id')->nullable()->constrained('fleet_drivers')->nullOnDelete();
            $table->timestamp('abastecido_em');
            $table->unsignedInteger('odometro');
            $table->string('combustivel', 20);                         // diesel_s10 | diesel_s500 | gasolina | etanol | gnv | outro
            $table->decimal('litros', 8, 2);
            $table->decimal('valor_litro', 8, 3);
            $table->decimal('valor_total', 12, 2);
            $table->boolean('tanque_cheio')->default(true);
            $table->string('origem', 20)->default('externo');          // externo (posto) | tanque_proprio
            $table->string('posto')->nullable();
            $table->string('nota_fiscal', 60)->nullable();
            $table->unsignedInteger('km_rodado')->nullable();          // desde o último tanque cheio (só quando este é tanque cheio)
            $table->decimal('consumo_km_l', 7, 2)->nullable();         // km_rodado ÷ litros abastecidos desde o último tanque cheio
            $table->text('observacoes')->nullable();
            $table->foreignUuid('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('peca_id')->nullable()->constrained('parts')->nullOnDelete();
            $table->foreignId('almoxarifado_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'ativo_id', 'abastecido_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frota_abastecimentos');
    }
};
