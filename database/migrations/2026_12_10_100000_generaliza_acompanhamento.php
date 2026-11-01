<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generaliza o acompanhamento do site: vira um cadastro de ACOMPANHAMENTOS (a remodelagem do site, uma pesquisa
 * futura etc.), cada um com sua lista de itens, notas e pendencias. Os dados que ja existiam ficam no primeiro
 * acompanhamento. Tabelas globais (sem tenant): controle interno da Oravel.
 */
return new class extends Migration
{
    private const STATUS_ITEM = ['publicada' => 'concluido', 'aprovada' => 'aprovado', 'em_revisao' => 'em_revisao', 'rascunho' => 'em_andamento', 'nao_convertida' => 'a_fazer'];

    public function up(): void
    {
        Schema::create('acompanhamentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('tipo')->default('projeto');   // projeto | pesquisa
            $table->string('status')->default('ativo');   // ativo | concluido | arquivado
            $table->string('base_url')->nullable();       // prefixo dos enderecos da lista (ex.: https://oravel.com.br)
            $table->string('previa_arquivo')->nullable();  // HTML no disco privado
            $table->string('previa_url')->nullable();      // link externo da previa
            $table->timestamps();
            $table->index('status');
        });

        Schema::rename('site_acompanhamento_notas', 'acompanhamento_notas');
        Schema::rename('site_acompanhamento_paginas', 'acompanhamento_itens');
        Schema::rename('site_acompanhamento_pendencias', 'acompanhamento_pendencias');

        Schema::table('acompanhamento_itens', function (Blueprint $table) {
            $table->dropUnique('site_acompanhamento_paginas_slug_unique');
            $table->renameColumn('slug', 'referencia');
        });
        DB::statement('alter table acompanhamento_itens alter column referencia drop not null');

        foreach (['acompanhamento_notas', 'acompanhamento_itens', 'acompanhamento_pendencias'] as $t) {
            Schema::table($t, fn (Blueprint $table) => $table->uuid('acompanhamento_id')->nullable()->index());
        }

        $id = (string) Str::uuid();
        $agora = now();
        $arquivo = null;
        $fonte = resource_path('previa-site/index.html');
        if (File::exists($fonte)) {
            $arquivo = "acompanhamentos/{$id}.html";
            Storage::disk('local')->put($arquivo, File::get($fonte));
        }
        DB::table('acompanhamentos')->insert([
            'id' => $id, 'nome' => 'Remodelagem do site institucional', 'tipo' => 'projeto', 'status' => 'ativo',
            'descricao' => 'Novo padrão do site oravel.com.br: prévia, status de cada página, pendências e decisões.',
            'base_url' => 'https://oravel.com.br', 'previa_arquivo' => $arquivo, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
        foreach (['acompanhamento_notas', 'acompanhamento_itens', 'acompanhamento_pendencias'] as $t) {
            DB::table($t)->update(['acompanhamento_id' => $id]);
            DB::statement("alter table {$t} alter column acompanhamento_id set not null");
        }
        foreach (self::STATUS_ITEM as $de => $para) {
            DB::table('acompanhamento_itens')->where('status', $de)->update(['status' => $para]);
        }
        DB::statement("alter table acompanhamento_itens alter column status set default 'a_fazer'");
    }

    public function down(): void
    {
        Schema::dropIfExists('acompanhamentos');
        Schema::rename('acompanhamento_notas', 'site_acompanhamento_notas');
        Schema::rename('acompanhamento_itens', 'site_acompanhamento_paginas');
        Schema::rename('acompanhamento_pendencias', 'site_acompanhamento_pendencias');
    }
};
