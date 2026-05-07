<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            // Removemos a coluna antiga, pois agora usamos category_id
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        // Se precisar reverter, adicionamos a coluna de volta
        Schema::table('materials', function (Blueprint $table) {
            $table->string('category')->nullable();
        });
    }
};