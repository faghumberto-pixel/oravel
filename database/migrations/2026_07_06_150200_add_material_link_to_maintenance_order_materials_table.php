<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * maintenance_order_materials gravava so um "name" texto livre -- sem
 * vinculo com o catalogo de Materiais, impossivel rastrear estoque, custo
 * confiavel ou numero de serie/garantia por peca aplicada. material_id e
 * nullable pra nao quebrar historico existente (que so tem o texto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_order_materials', function (Blueprint $table) {
            $table->foreignUuid('material_id')->nullable()->after('maintenance_order_id')->constrained()->nullOnDelete();
            $table->string('serial_number')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_order_materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
            $table->dropColumn('serial_number');
        });
    }
};
