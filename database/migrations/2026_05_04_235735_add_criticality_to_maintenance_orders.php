<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->foreignUuid('criticality_level_id')->nullable()->constrained('criticality_levels');
        });
    }
    public function down(): void {
        Schema::table('maintenance_orders', function (Blueprint $table) {
            $table->dropColumn('criticality_level_id');
        });
    }
};
