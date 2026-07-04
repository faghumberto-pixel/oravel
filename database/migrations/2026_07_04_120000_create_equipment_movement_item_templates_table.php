<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_movement_item_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->string('section');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('requires_photo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_movement_item_templates');
    }
};
