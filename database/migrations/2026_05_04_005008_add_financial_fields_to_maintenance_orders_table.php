<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->decimal('logistics_cost', 10, 2)->default(0)->after('km_traveled');
            $table->decimal('labor_cost', 10, 2)->default(0)->after('logistics_cost');
            $table->decimal('material_cost', 10, 2)->default(0)->after('labor_cost');
            $table->decimal('total_order_cost', 10, 2)->default(0)->after('material_cost');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn(['logistics_cost', 'labor_cost', 'material_cost', 'total_order_cost']);
        });
    }
};