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
            // 1. Remove as colunas antigas que causavam conflitos de ID/404
            // Nota: Se alguma dessas colunas não existir no seu banco, o Laravel pode dar erro.
            // Se necessário, você pode envolver em um if(Schema::hasColumn...)
            $table->dropColumn([
                'asset_category_id', 
                'criticality_level_id', 
                'checklist_group_id'
            ]);

            // 2. Adiciona os novos campos de texto para a lista padronizada via código
            $table->string('asset_category')->nullable()->after('name');
            $table->string('criticality_level')->nullable()->after('asset_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Reverte a criação dos campos de texto
            $table->dropColumn(['asset_category', 'criticality_level']);

            // Adiciona as colunas originais de volta (caso precise dar rollback)
            $table->foreignId('asset_category_id')->nullable();
            $table->foreignId('criticality_level_id')->nullable();
            $table->foreignId('checklist_group_id')->nullable();
        });
    }
};