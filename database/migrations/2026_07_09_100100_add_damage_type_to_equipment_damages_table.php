<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment_damages', function (Blueprint $table) {
            // Mesmo padrão de `severity`: string livre validada por
            // constantes no model (App\Models\EquipmentDamage::DAMAGE_TYPE_*),
            // não FK — lista pequena e não precisa ser customizável por tenant.
            // Nullable porque avarias já registradas não têm essa classificação
            // (o relatório de reincidência trata NULL como "não classificado").
            $table->string('damage_type')->nullable()->after('severity');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_damages', function (Blueprint $table) {
            $table->dropColumn('damage_type');
        });
    }
};
