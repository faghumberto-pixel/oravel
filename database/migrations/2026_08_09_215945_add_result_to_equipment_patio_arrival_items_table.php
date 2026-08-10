<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mesmo motivo/padrao da migration irma em equipment_movement_items --
     * EquipmentPatioArrivalItem e' copia estrutural desse model.
     */
    public function up(): void
    {
        Schema::table('equipment_patio_arrival_items', function (Blueprint $table) {
            $table->string('result')->nullable()->after('is_checked');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_patio_arrival_items', function (Blueprint $table) {
            $table->dropColumn('result');
        });
    }
};
