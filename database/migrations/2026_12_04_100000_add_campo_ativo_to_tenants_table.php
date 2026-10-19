<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Área de campo (celular, /campo): desligada para todas as empresas existentes; a Central liga empresa por empresa. */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('campo_ativo')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('campo_ativo');
        });
    }
};
