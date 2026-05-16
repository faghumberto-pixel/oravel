<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                // Alterado para uuid para ser compatível com a relação nos Tenants
                $table->uuid('id')->primary(); 
                $table->string('name');
                $table->decimal('price', 10, 2);
                $table->string('billing_cycle')->default('monthly');
                $table->json('features')->nullable(); 
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};