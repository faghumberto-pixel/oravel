<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('freight_records', function (Blueprint $table) {
            $table->decimal('horas_motorista', 6, 2)->nullable()->after('km_percorrido');
            $table->decimal('custo_motorista', 10, 2)->nullable()->after('horas_motorista');
        });
    }

    public function down(): void
    {
        Schema::table('freight_records', function (Blueprint $table) {
            $table->dropColumn(['horas_motorista', 'custo_motorista']);
        });
    }
};
