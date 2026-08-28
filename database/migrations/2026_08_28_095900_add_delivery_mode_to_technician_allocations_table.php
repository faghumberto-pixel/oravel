<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nem todo tecnico usa o app -- alguns recebem a OS impressa em papel
 * (sem sinal em campo). 'digital' (default): tecnico confirma pelo app
 * (TechnicianDailyTasks). 'impressa': o ato de imprimir ja marca a
 * alocacao como confirmada (AlocacaoTecnicosPmp::printAllocation()), sem
 * passo de aceite digital.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technician_allocations', function (Blueprint $table) {
            $table->string('delivery_mode')->default('digital')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('technician_allocations', function (Blueprint $table) {
            $table->dropColumn('delivery_mode');
        });
    }
};
