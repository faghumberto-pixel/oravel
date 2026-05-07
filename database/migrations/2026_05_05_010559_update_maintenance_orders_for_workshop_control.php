<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_orders', 'is_rework')) {
                $table->boolean('is_rework')->default(false);
            }
            if (!Schema::hasColumn('maintenance_orders', 'parent_os_id')) {
                $table->foreignUuid('parent_os_id')->nullable()->constrained('maintenance_orders');
            }
            if (!Schema::hasColumn('maintenance_orders', 'last_timer_start')) {
                $table->timestamp('last_timer_start')->nullable();
            }
            if (!Schema::hasColumn('maintenance_orders', 'total_time_seconds')) {
                $table->bigInteger('total_time_seconds')->default(0);
            }
        });
    }
    public function down(): void {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn(['is_rework', 'parent_os_id', 'last_timer_start', 'total_time_seconds']);
        });
    }
};
