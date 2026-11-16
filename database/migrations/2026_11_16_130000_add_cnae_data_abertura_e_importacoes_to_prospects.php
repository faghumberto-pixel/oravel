<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prospects: guarda o codigo do CNAE e a data de abertura COMPLETA (antes so a descricao do CNAE e o ano),
 * e cria o historico das importacoes feitas pela tela da Central (quem, quando, arquivo, totais).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('cnae_codigo', 12)->nullable()->after('atividade');
            $table->date('data_abertura')->nullable()->after('ano_abertura');
        });

        Schema::create('prospect_importacoes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('arquivo', 255);
            $table->string('origem', 120)->nullable();
            $table->unsignedInteger('lote')->nullable();
            $table->string('se_existir', 20);
            $table->unsignedInteger('criados')->default(0);
            $table->unsignedInteger('atualizados')->default(0);
            $table->unsignedInteger('ignorados')->default(0);
            $table->unsignedInteger('erros')->default(0);
            $table->unsignedInteger('avisos')->default(0);
            $table->json('relatorio')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_importacoes');

        Schema::table('prospects', function (Blueprint $table) {
            $table->dropColumn(['cnae_codigo', 'data_abertura']);
        });
    }
};
