<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Veículo (caminhão): tipo de carroceria (baú, carga aberta, implemento...), detalhe livre e quantidade de eixos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('carroceria_tipo', 30)->nullable();
            $table->string('carroceria_detalhe', 191)->nullable();
            $table->unsignedTinyInteger('quantidade_eixos')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['carroceria_tipo', 'carroceria_detalhe', 'quantidade_eixos']);
        });
    }
};
