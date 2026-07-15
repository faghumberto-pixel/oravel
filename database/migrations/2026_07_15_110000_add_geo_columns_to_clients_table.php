<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geocodificado a partir de zip_code/address (CepGeocodingService::
 * geocodeAddress(), ver ClientResource form) -- alimenta o fallback de
 * localizacao do Mapa de Equipamentos (AssetMapWidget) pra Ativos sem
 * CEP proprio mas vinculados a um Cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
