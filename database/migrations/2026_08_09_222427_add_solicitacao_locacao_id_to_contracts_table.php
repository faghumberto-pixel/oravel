<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gap apontado no diagnostico de Geradores de Energia: o "combo" de
     * ativos (gerador + cabo + QTA, via SolicitacaoLocacao::assets(), N:N)
     * so' existe na fase comercial -- Contract.asset_id e' um unico FK,
     * sem vinculo entre os N contratos gerados do mesmo combo fechado.
     * Mesmo padrao ja usado em equipment_movements.solicitacao_locacao_id
     * -- so' agrupa, nao gera contratos automaticamente (fora de escopo
     * desta migration).
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignUuid('solicitacao_locacao_id')->nullable()
                ->after('asset_id')
                ->constrained('solicitacoes_locacao')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('solicitacao_locacao_id');
        });
    }
};
