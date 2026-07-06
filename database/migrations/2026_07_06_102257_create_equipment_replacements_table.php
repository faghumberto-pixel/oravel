<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_replacements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('maintenance_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('equipment_damage_id')->nullable()->constrained();
            $table->foreignUuid('original_asset_id')->constrained('assets');
            $table->foreignUuid('replacement_asset_id')->nullable()->constrained('assets');
            $table->foreignUuid('requested_by_user_id')->constrained('users');
            $table->string('urgency')->default('normal');
            $table->text('reason');
            $table->string('status')->default('solicitado');
            $table->foreignUuid('identified_by_user_id')->nullable()->constrained('users');
            $table->timestamp('identified_at')->nullable();
            $table->foreignUuid('desmobilization_movement_id')->nullable()->constrained('equipment_movements');
            $table->foreignUuid('mobilization_movement_id')->nullable()->constrained('equipment_movements');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_replacements');
    }
};
