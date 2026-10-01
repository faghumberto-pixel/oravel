<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rótulo usado para AGRUPAR a tela de backups por cliente (nome do cliente, "Plataforma" ou "Geral (antigo)").
        Schema::table('database_backups', function (Blueprint $table) {
            $table->string('client_label')->nullable()->index();
        });

        DB::table('database_backups')->where('kind', 'full')->update(['client_label' => 'Geral (antigo)']);
    }

    public function down(): void
    {
        Schema::table('database_backups', fn (Blueprint $table) => $table->dropColumn('client_label'));
    }
};
