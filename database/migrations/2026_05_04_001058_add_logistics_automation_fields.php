<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajuste nos Ativos para identificar Veículos
        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'is_vehicle')) {
                $table->boolean('is_vehicle')->default(false)->after('status');
            }
            if (!Schema::hasColumn('assets', 'cost_per_km')) {
                $table->decimal('cost_per_km', 10, 2)->default(0)->after('is_vehicle');
            }
        });

        // Ajuste na OS para vincular o transporte
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_orders', 'transport_vehicle_id')) {
                $table->foreignUuid('transport_vehicle_id')
                    ->nullable()
                    ->after('technician_id')
                    ->constrained('assets')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('maintenance_orders', 'km_traveled')) {
                $table->decimal('km_traveled', 10, 2)->default(0)->after('transport_vehicle_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropForeign(['transport_vehicle_id']);
            $table->dropColumn(['transport_vehicle_id', 'km_traveled']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['is_vehicle', 'cost_per_km']);
        });
    }
};