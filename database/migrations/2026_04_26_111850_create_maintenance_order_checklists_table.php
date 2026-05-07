<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_order_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('maintenance_order_id')->constrained('maintenance_orders')->onDelete('cascade');
            $table->string('category');
            $table->string('item_name');
            $table->text('instructions')->nullable();
            $table->boolean('is_completed')->default(false); // Checkbox
            $table->text('notes')->nullable(); // Para o técnico escrever ou ditar
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_checklists');
    }
};