<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('material_categories', function (Blueprint $table) {
            // Adiciona a coluna deleted_at necessária para a trait SoftDeletes
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_categories', function (Blueprint $table) {
            // Remove a coluna caso você precise reverter a migration
            $table->dropSoftDeletes();
        });
    }
};