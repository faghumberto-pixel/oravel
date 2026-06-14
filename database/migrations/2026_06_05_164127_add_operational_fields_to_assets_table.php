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
            // Adiciona criticidade (1: Baixa, 5: Alta) para priorização comercial/oficina
            if (!Schema::hasColumn('assets', 'criticidade_peso')) {
                $table->integer('criticidade_peso')->default(1)->comment('1:Baixa, 5:Alta');
            }
            
            // Adiciona horímetro e odômetro com precisão decimal para manutenções rigorosas
            if (!Schema::hasColumn('assets', 'horimetro_atual')) {
                $table->decimal('horimetro_atual', 12, 2)->default(0.00);
            }
            
            if (!Schema::hasColumn('assets', 'odometro_atual')) {
                $table->decimal('odometro_atual', 12, 2)->default(0.00);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['criticidade_peso', 'horimetro_atual', 'odometro_atual']);
        });
    }
};