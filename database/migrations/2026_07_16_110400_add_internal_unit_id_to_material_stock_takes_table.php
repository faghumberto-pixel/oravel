<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario passa a ser por filial (contagem fisica so faz sentido num
 * lugar por vez) -- nullable pra nao quebrar inventarios ja finalizados
 * antes desta mudanca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_stock_takes', function (Blueprint $table) {
            $table->foreignUuid('internal_unit_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_stock_takes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('internal_unit_id');
        });
    }
};
