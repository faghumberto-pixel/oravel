<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            // Adiciona a coluna APENAS se ela não existir no Postgres
            if (!Schema::hasColumn('contracts', 'contract_number')) {
                $table->string('contract_number')->unique()->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('contract_number');
        });
    }
};