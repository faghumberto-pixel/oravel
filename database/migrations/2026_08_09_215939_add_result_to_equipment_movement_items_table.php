<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antes so' havia is_checked (boolean simples: marcado/nao marcado) --
     * insuficiente pra uma inspecao formal tipo NR-18/NR-35, que exige
     * resultado categorizado por item (ex: sensor de inclinacao, parada de
     * emergencia). is_checked continua controlando progresso/trava de
     * finalizacao; result e' um dado adicional por item, nullable.
     */
    public function up(): void
    {
        Schema::table('equipment_movement_items', function (Blueprint $table) {
            $table->string('result')->nullable()->after('is_checked');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_movement_items', function (Blueprint $table) {
            $table->dropColumn('result');
        });
    }
};
