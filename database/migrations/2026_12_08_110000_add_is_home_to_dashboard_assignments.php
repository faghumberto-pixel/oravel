<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marca a atribuição que vira a tela Início da empresa (no lugar do Painel de Controle e da Gestão à Vista).
     * Nenhuma atribuição marcada = a empresa segue com o Início padrão.
     */
    public function up(): void
    {
        Schema::table('dashboard_assignments', function (Blueprint $table) {
            $table->boolean('is_home')->default(false)->after('dashboard_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_assignments', function (Blueprint $table) {
            $table->dropColumn('is_home');
        });
    }
};
