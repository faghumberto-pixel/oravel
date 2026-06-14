<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Força o PostgreSQL a tornar a coluna 'price' opcional (nullable)
        // Isso resolve a violação de constraint NOT NULL
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable()->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->nullable(false)->change();
        });
    }
};