<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_movement_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('equipment_movement_id')->constrained()->cascadeOnDelete();
            $table->string('section');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('requires_photo')->default(false);
            $table->boolean('is_checked')->default(false);
            $table->string('value')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_movement_items');
    }
};
