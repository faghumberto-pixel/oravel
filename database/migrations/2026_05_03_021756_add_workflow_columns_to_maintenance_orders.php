<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            // Verifica uma por uma. Se não existir, ele cria!
            if (!Schema::hasColumn('maintenance_orders', 'started_at')) {
                $table->timestamp('started_at')->nullable();
            }
            if (!Schema::hasColumn('maintenance_orders', 'finished_at')) {
                $table->timestamp('finished_at')->nullable();
            }
            if (!Schema::hasColumn('maintenance_orders', 'rescheduled_to')) {
                $table->timestamp('rescheduled_to')->nullable();
            }
            if (!Schema::hasColumn('maintenance_orders', 'total_time_seconds')) {
                $table->integer('total_time_seconds')->default(0);
            }
            if (!Schema::hasColumn('maintenance_orders', 'last_timer_start')) {
                $table->timestamp('last_timer_start')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $columnsToDrop = [];
            
            if (Schema::hasColumn('maintenance_orders', 'started_at')) $columnsToDrop[] = 'started_at';
            if (Schema::hasColumn('maintenance_orders', 'finished_at')) $columnsToDrop[] = 'finished_at';
            if (Schema::hasColumn('maintenance_orders', 'rescheduled_to')) $columnsToDrop[] = 'rescheduled_to';
            if (Schema::hasColumn('maintenance_orders', 'total_time_seconds')) $columnsToDrop[] = 'total_time_seconds';
            if (Schema::hasColumn('maintenance_orders', 'last_timer_start')) $columnsToDrop[] = 'last_timer_start';

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};