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
        Schema::table('maintenance_order_materials', function (Blueprint $table) {
            // Adicionando a coluna de preço unitário com precisão decimal para valores monetários
            $table->decimal('unit_price', 15, 2)->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_order_materials', function (Blueprint $table) {
            // Removendo a coluna caso seja necessário desfazer a migração
            $table->dropColumn('unit_price');
        });
    }
};