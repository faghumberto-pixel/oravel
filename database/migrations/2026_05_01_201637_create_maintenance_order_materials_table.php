<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_order_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('maintenance_order_id');
            $table->uuid('tenant_id');
            $table->string('name');
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_order_materials');
    }
};