<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->boolean('is_rework')->default(false);
            $table->foreignUuid('parent_os_id')->nullable()->constrained('maintenance_orders');
        });
    }
    public function down(): void {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn(['is_rework', 'parent_os_id']);
        });
    }
};
