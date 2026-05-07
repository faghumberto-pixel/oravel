<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_order_material', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Usando UUID conforme seu padrão
            $table->foreignUuid('maintenance_order_id')->constrained('maintenance_orders')->onDelete('cascade');
            $table->foreignUuid('material_id')->constrained('materials')->onDelete('cascade');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('cost_at_time', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_material');
    }
};