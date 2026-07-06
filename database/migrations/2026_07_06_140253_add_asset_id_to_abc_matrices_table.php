<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O model AbcMatrix ja assumia asset_id (relacao asset(), fillable),
     * mas a coluna nunca foi criada -- a classificacao ABC era so um
     * cadastro generico A/B/C, sem vinculo com nenhum ativo especifico.
     * Sem registros reais ainda (tabela vazia em DEV), entao pode ser
     * obrigatoria direto, uma classificacao por ativo por tenant.
     */
    public function up(): void
    {
        Schema::table('abc_matrices', function (Blueprint $table) {
            $table->foreignUuid('asset_id')->after('tenant_id')->constrained();
            $table->unique(['tenant_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::table('abc_matrices', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'asset_id']);
            $table->dropConstrainedForeignId('asset_id');
        });
    }
};
