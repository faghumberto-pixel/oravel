<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('materials')) {
            Schema::create('materials', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('sku')->unique();
                $table->string('name');
                $table->string('category');
                $table->decimal('unit_cost', 10, 2);
                $table->integer('min_stock')->default(0);
                $table->integer('max_stock')->default(0);
                $table->integer('current_stock')->default(0);
                $table->string('ncm')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};