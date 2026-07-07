<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Combo" de ativos numa mesma Solicitacao de Locacao (ex: gerador +
 * mini-carregadeira pro mesmo canteiro, ou um lote de N maquinas
 * identicas). O campo asset_id legado na tabela principal continua
 * existindo pra solicitacao de um unico equipamento especifico -- este
 * pivot e aditivo, nao substitui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacao_locacao_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('solicitacao_locacao_id')->constrained('solicitacoes_locacao')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['solicitacao_locacao_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_locacao_assets');
    }
};
