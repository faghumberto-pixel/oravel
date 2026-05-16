<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('fleet_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('asset_id')->unique()->constrained()->onDelete('cascade');
            $table->boolean('is_available')->default(false);
            $table->string('capacity_label')->nullable(); // Ex: "12 metros", "500kVA"
            $table->foreignUuid('last_maintenance_id')->nullable()->constrained('maintenance_orders');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('fleet_statuses'); }
};
