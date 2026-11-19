<?php

namespace App\Relacionamento\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Regra "faixa de score => proxima acao comercial". Editavel na Central. Global. */
class RelAcaoRegra extends Model
{
    use HasUuids;

    /** [min, max, titulo, descricao]. Semeadas na migration. */
    public const PADRAO = [
        [80, 100, 'Sugerir reunião', 'Relacionamento excelente: proponha uma conversa para apresentar a Oravel.'],
        [60, 79, 'Enviar vídeo', 'Já conhece o conteúdo: mande um vídeo ligado ao tema de interesse.'],
        [30, 59, 'Enviar artigo', 'Engajamento médio: mande um artigo do tema de interesse para manter a conversa.'],
        [0, 29, 'Enviar ferramenta', 'Relacionamento baixo: convide a usar uma ferramenta gratuita do oravel.org.'],
    ];

    protected $table = 'rel_acao_regras';

    protected $guarded = [];

    protected $casts = ['ativo' => 'boolean', 'score_min' => 'integer', 'score_max' => 'integer'];
}
