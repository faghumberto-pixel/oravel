<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            // Adiciona o tenant_id (usamos uuid para manter a consistência)
            $table->uuid('tenant_id')->nullable();
            
            // Adiciona a coluna is_template que usamos no Seeder
            $table->boolean('is_template')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_order_checklists', function (Blueprint $table) {
            $table->dropColumn(['tenant_id', 'is_template']);
        });
    }
};