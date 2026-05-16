<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_orders', 'horimetro_entry')) {
                $table->decimal('horimetro_entry', 10, 2)->nullable()->after('status');
            }
            if (!Schema::hasColumn('maintenance_orders', 'fuel_level')) {
                $table->string('fuel_level')->nullable()->after('horimetro_entry');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (Schema::hasColumn('maintenance_orders', 'horimetro_entry')) {
                $table->dropColumn('horimetro_entry');
            }
            if (Schema::hasColumn('maintenance_orders', 'fuel_level')) {
                $table->dropColumn('fuel_level');
            }
        });
    }
};
