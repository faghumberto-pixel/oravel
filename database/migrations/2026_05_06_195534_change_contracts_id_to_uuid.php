<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // No Postgres, para mudar de BIGINT para UUID com a tabela em uso, 
        // o caminho mais seguro é remover e recriar a coluna.
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            // Criamos o ID como UUID e definimos como Primary Key novamente
            $table->uuid('id')->primary()->first();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('id');
        });
        Schema::table('contracts', function (Blueprint $table) {
            $table->id()->first();
        });
    }
};