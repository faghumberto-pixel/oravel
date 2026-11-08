<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Perfil de Acesso (funcao) escolhe o Painel de Controle da pessoa: financeiro, comercial, tecnico, rh... Nulo = Painel de Controle geral. Aditiva. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'painel_area')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('painel_area', 30)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'painel_area')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('painel_area');
            });
        }
    }
};
