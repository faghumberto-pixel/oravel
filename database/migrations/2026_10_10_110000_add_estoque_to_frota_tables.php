<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Fase 4B: liga pneus, baterias e óleo ao Almoxarifado (Peças e Insumos + saldo por almoxarifado). Tudo opcional. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['frota_pneus', 'frota_baterias', 'frota_trocas_oleo'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->foreignId('peca_id')->nullable()->constrained('parts')->nullOnDelete();
                $table->foreignId('almoxarifado_id')->nullable()->constrained('warehouses')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['frota_pneus', 'frota_baterias', 'frota_trocas_oleo'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropConstrainedForeignId('peca_id');
                $table->dropConstrainedForeignId('almoxarifado_id');
            });
        }
    }
};
