<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // O comando ->change() permite modificar uma coluna existente.
            // Adicionamos ->nullable() para permitir que ela fique vazia.
            $table->string('patrimonio')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Reverte a alteração caso precise voltar ao estado original (NOT NULL).
            $table->string('patrimonio')->nullable(false)->change();
        });
    }
};