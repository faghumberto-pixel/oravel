<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custo de transporte agregado (frete + pedagios do frete), recalculado
     * pelos Observers de FreightRecord/FleetTollRecord -- nao editavel a mao.
     */
    public function up(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->decimal('custo_transporte', 10, 2)->default(0)->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('equipment_movements', function (Blueprint $table) {
            $table->dropColumn('custo_transporte');
        });
    }
};
