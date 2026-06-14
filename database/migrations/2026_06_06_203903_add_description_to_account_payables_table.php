<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            // Verifica se a coluna já existe antes de tentar criar
            if (!Schema::hasColumn('account_payables', 'description')) {
                $table->string('description')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('account_payables', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};