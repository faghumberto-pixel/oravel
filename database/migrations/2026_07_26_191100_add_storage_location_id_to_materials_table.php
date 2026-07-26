<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Complementa (nao substitui) warehouse_location -- que continua sendo
     * um texto livre pra quem nao quiser cadastrar posicao estruturada.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->foreignUuid('storage_location_id')->nullable()->after('warehouse_location')
                ->constrained('storage_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_location_id');
        });
    }
};
