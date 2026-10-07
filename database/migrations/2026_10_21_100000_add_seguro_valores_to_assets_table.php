<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Valores do seguro do veículo: franquias (colisão e vidros) e coberturas (própria e de terceiros). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->decimal('seguro_franquia_colisao', 12, 2)->nullable();
            $table->decimal('seguro_franquia_vidros', 12, 2)->nullable();
            $table->decimal('seguro_valor_cobertura', 12, 2)->nullable();
            $table->decimal('seguro_cobertura_terceiros', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['seguro_franquia_colisao', 'seguro_franquia_vidros', 'seguro_valor_cobertura', 'seguro_cobertura_terceiros']);
        });
    }
};
