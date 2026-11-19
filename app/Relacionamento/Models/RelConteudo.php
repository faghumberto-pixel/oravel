<?php

namespace App\Relacionamento\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Catalogo de conteudos do oravel.org (artigo, video, ferramenta, pdf). Global. */
class RelConteudo extends Model
{
    use HasUuids;

    protected $table = 'rel_conteudos';

    protected $guarded = [];

    protected $casts = ['ativo' => 'boolean'];
}
