<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * data_saida_prevista deixa de ser sempre obrigatoria no banco -- agora e'
 * so exigida quando a solicitacao nao tem contract_id (regra aplicada em
 * App\Models\SolicitacaoLocacao::booted()/saving()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes_locacao', function (Blueprint $table) {
            $table->date('data_saida_prevista')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes_locacao', function (Blueprint $table) {
            $table->date('data_saida_prevista')->nullable(false)->change();
        });
    }
};
