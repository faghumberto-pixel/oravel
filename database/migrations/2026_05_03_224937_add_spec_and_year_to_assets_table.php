<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Adicionando as novas colunas sem excluir nada
            $table->string('specification')->nullable()->after('description');
            $table->integer('manufacturing_year')->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['specification', 'manufacturing_year']);
        });
    }
};