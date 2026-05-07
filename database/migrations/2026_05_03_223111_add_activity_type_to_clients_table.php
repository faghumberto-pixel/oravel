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
        Schema::table('clients', function (Blueprint $table) {
            // INCLUSÃO MÍNIMA: Adiciona o campo após a coluna 'name'
            $table->string('activity_type')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Reverte a ação caso precise dar um rollback no banco
            $table->dropColumn('activity_type');
        });
    }
};