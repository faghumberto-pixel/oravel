<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_damages', function (Blueprint $table) {
            // Suporta a query de reincidência: "mesmo ativo, avarias nos
            // últimos N dias". No Postgres, foreignUuid()->constrained() não
            // cria índice B-tree automático na coluna FK — sem este índice a
            // query cai em sequential scan.
            $table->index(['tenant_id', 'asset_id', 'created_at'], 'equipment_damages_reincidencia_idx');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_damages', function (Blueprint $table) {
            $table->dropIndex('equipment_damages_reincidencia_idx');
        });
    }
};
