<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Jornada diária esperada (horas) -- base pra calcular horas
            // extras em App\Services\TimeClockHoursCalculator. 8h e' o
            // padrão CLT mais comum, editável por colaborador.
            $table->decimal('daily_work_hours', 4, 2)->default(8)->after('admission_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('daily_work_hours');
        });
    }
};
