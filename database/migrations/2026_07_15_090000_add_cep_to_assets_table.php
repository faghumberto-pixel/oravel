<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CEP proprio do equipamento -- alimenta o mapa de equipamentos
 * (AssetMapWidget), geocodificado automaticamente pro latitude/longitude
 * ja existentes via CepGeocodingService, mesmo padrao ja usado em
 * Contract::cep_obra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('cep')->nullable()->after('specification');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('cep');
        });
    }
};
