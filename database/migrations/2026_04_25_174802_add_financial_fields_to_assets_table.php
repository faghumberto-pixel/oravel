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
            // Valor original pago pelo ativo
            $table->decimal('acquisition_value', 15, 2)->nullable();
            
            // Valor que se espera obter ao final da vida útil (sucata)
            $table->decimal('residual_value', 15, 2)->default(0);
            
            // Estimativa de anos de vida útil (usado para calcular a depreciação linear)
            $table->integer('useful_life_years')->default(10);
            
            // Data da compra para cálculos de depreciação acumulada
            $table->date('acquisition_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'acquisition_value', 
                'residual_value', 
                'useful_life_years', 
                'acquisition_date'
            ]);
        });
    }
};