<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Previsão no contrato de quais Itens Agregados o cliente quer na locação.
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('includes_insumos')->default(false);
            $table->boolean('includes_acessorios')->default(false);
            $table->boolean('includes_mao_de_obra')->default(false);
            $table->boolean('includes_seguranca_docs')->default(false);
            $table->text('aggregate_items_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['includes_insumos', 'includes_acessorios', 'includes_mao_de_obra', 'includes_seguranca_docs', 'aggregate_items_notes']);
        });
    }
};
