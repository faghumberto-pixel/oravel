<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traccar_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('traccar_device_id');
            $table->string('identifier');
            $table->timestamp('created_at')->nullable();

            $table->unique(['tenant_id', 'traccar_device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traccar_devices');
    }
};
