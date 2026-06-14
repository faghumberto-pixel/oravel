<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Adicionando todas as colunas que o erro está reclamando
            if (!Schema::hasColumn('plans', 'level')) $table->integer('level')->default(1);
            if (!Schema::hasColumn('plans', 'base_price')) $table->decimal('base_price', 10, 2)->default(0);
            if (!Schema::hasColumn('plans', 'discount_type')) $table->string('discount_type')->nullable();
            if (!Schema::hasColumn('plans', 'discount_value')) $table->decimal('discount_value', 10, 2)->default(0);
            if (!Schema::hasColumn('plans', 'campaign_tag')) $table->string('campaign_tag')->nullable();
            if (!Schema::hasColumn('plans', 'is_active')) $table->boolean('is_active')->default(true);
            if (!Schema::hasColumn('plans', 'features')) $table->json('features')->nullable();
        });
    }

    public function down(): void
    {
        // Se precisar reverter, remove as colunas
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['level', 'base_price', 'discount_type', 'discount_value', 'campaign_tag', 'is_active', 'features']);
        });
    }
};