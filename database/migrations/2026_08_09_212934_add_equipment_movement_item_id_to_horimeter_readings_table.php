<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rastreia de qual item de checklist (EquipmentMovementItem, ex:
     * "Horímetro de saída"/"Horímetro de retorno") uma leitura de
     * SOURCE_CHECKLIST veio -- sem isso, reabrir e salvar o mesmo item de
     * novo (ex: corrigir um valor digitado errado) criaria uma leitura
     * duplicada em vez de atualizar a existente.
     */
    public function up(): void
    {
        Schema::table('horimeter_readings', function (Blueprint $table) {
            $table->foreignUuid('equipment_movement_item_id')->nullable()
                ->after('asset_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('horimeter_readings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('equipment_movement_item_id');
        });
    }
};
