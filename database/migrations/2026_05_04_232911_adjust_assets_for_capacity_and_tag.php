<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Torna a tag opcional no banco para evitar o erro de violação
            $table->string('tag')->nullable()->change();
            
            // Novos campos técnicos
            $table->string('capacity_value')->nullable();
            $table->string('capacity_unit')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('tag')->nullable(false)->change();
            $table->dropColumn(['capacity_value', 'capacity_unit']);
        });
    }
};
