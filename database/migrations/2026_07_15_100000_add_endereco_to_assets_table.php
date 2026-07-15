<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Texto do endereco resolvido pelo ViaCEP junto do cep (2026_07_15_090000) --
 * ate agora o CepGeocodingService::lookupCep() so usava o endereco pra
 * montar a query de geocoding, nunca persistia. Alimenta a exportacao do
 * Mapa de Equipamentos (AssetMapExporter).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('endereco')->nullable()->after('cep');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('endereco');
        });
    }
};
