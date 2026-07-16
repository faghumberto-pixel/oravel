<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de portaria passa a cobrir tambem SAIDA (nao so' chegada) --
 * mesma tabela/model, so' diferencia a direcao. reason continua sendo a
 * mesma coluna pras duas direcoes (o form filtra quais opcoes mostrar
 * conforme a direcao escolhida).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patio_entries', function (Blueprint $table) {
            $table->string('direction')->default('entrada')->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('patio_entries', function (Blueprint $table) {
            $table->dropColumn('direction');
        });
    }
};
