<?php

namespace App\Relacionamento\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Peso de um tipo de interacao no score. Editavel na Central. Global. */
class RelScoreRegra extends Model
{
    use HasUuids;

    /** tipo => [pontos, limite de ocorrencias]. Valores iniciais (semeados na migration) e fallback se a tabela estiver vazia. */
    public const PADRAO = [
        RelInteracao::TIPO_VISITA => [5, 3],
        RelInteracao::TIPO_ARTIGO => [10, 3],
        RelInteracao::TIPO_VIDEO => [10, 3],
        RelInteracao::TIPO_FERRAMENTA => [20, 3],
        RelInteracao::TIPO_PDF => [15, 2],
        RelInteracao::TIPO_GAZETA => [30, 1],
        RelInteracao::TIPO_EMAIL_ABERTO => [5, 3],
        RelInteracao::TIPO_EMAIL_CLIQUE => [8, 3],
        RelInteracao::TIPO_WHATSAPP_RESPOSTA => [25, 2],
        RelInteracao::TIPO_REUNIAO => [50, 1],
        RelInteracao::TIPO_CONTATO_COMERCIAL => [0, 0],
    ];

    protected $table = 'rel_score_regras';

    protected $guarded = [];

    protected $casts = ['ativo' => 'boolean', 'pontos' => 'integer', 'limite' => 'integer'];
}
