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
        Schema::table('assets', function (Blueprint $table) {
            // Criamos a chave estrangeira para a categoria
            // Usamos nullable() para que ativos já existentes não causem erro na migração
            $table->foreignId('asset_category_id')
                ->nullable()
                ->after('tenant_id') // Organiza a coluna logo após o tenant_id
                ->constrained('asset_categories')
                ->onDelete('set null'); // Se a categoria for excluída, o ativo apenas fica "sem categoria"
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Removemos a restrição de chave estrangeira primeiro
            $table->dropForeign(['asset_category_id']);
            // Depois removemos a coluna
            $table->dropColumn('asset_category_id');
        });
    }
};