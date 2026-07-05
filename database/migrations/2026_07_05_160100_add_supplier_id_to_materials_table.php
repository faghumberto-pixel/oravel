<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Fornecedor Homologado" no form do MaterialResource ja tentava salvar
     * supplier_id, mas a coluna nunca existiu -- por isso o campo nunca
     * persistia nada de verdade.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->foreignUuid('supplier_id')->nullable()->after('material_category_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
