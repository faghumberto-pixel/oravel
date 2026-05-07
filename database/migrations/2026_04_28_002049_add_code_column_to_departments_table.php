<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            // Adiciona a coluna 'code' se ela ainda não existir
            if (!Schema::hasColumn('departments', 'code')) {
                $table->string('code', 20)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};