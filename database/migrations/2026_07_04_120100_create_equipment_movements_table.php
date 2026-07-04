<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('maintenance_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained();
            $table->string('type');
            $table->string('status')->default('aguardando_vistoria');
            $table->decimal('vistoria_geral_lat', 10, 8)->nullable();
            $table->decimal('vistoria_geral_lng', 11, 8)->nullable();
            $table->timestamp('vistoria_geral_captured_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_movements');
    }
};
