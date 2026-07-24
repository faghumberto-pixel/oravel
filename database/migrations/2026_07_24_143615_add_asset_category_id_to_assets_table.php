<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Reconecta Asset a AssetCategory de verdade -- a FK antiga
            // (asset_category_id) foi removida em 2026-05-09 em favor de um
            // texto livre (asset_category), deixando AssetCategory::assets()
            // apontando pra uma coluna que não existia mais. O texto livre
            // continua existindo (não foi dropado, várias telas ainda leem
            // dele) -- esta FK nova vira o campo oficial daqui pra frente,
            // mantido em sincronia pelo form de AssetResource.
            $table->foreignUuid('asset_category_id')->nullable()->after('asset_category')
                ->constrained('asset_categories')->nullOnDelete();
        });

        // Backfill seguro: só casa quando o texto livre bate exatamente
        // (case-insensitive, mesmo tenant) com o nome de uma AssetCategory
        // já cadastrada -- nada de fuzzy matching aqui, o resto fica null
        // pra ajuste manual (AssetResource passa a ter o Select certo daqui
        // pra frente, então essa lacuna não deve crescer).
        DB::statement(<<<'SQL'
            UPDATE assets
            SET asset_category_id = asset_categories.id
            FROM asset_categories
            WHERE assets.tenant_id = asset_categories.tenant_id
              AND lower(trim(assets.asset_category)) = lower(trim(asset_categories.name))
              AND assets.asset_category_id IS NULL
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_category_id');
        });
    }
};
