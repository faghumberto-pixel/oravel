<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Executa as modificações no banco de dados.
     */
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            // Adiciona a coluna tenant_id como foreignUuid para manter a compatibilidade com seu sistema
            // Usamos nullable() inicialmente caso já existam registros na tabela
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->constrained('tenants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverte as modificações no banco de dados.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            // Remove a chave estrangeira e a coluna em caso de rollback
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};