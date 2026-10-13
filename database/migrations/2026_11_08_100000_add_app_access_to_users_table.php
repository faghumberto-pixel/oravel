<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Funções do App do Colaborador liberadas para cada usuário (lista de chaves de App\Support\Colaborador\Modulos,
     * ex.: "logistica", "logistica.abastecimento"). NULL = nunca configurado: vale o padrão de sempre (perfil + permissão).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('app_access')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('app_access');
        });
    }
};
