<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item de uma lista de acompanhamento (uma pagina do site, uma fonte de pesquisa, um concorrente...). */
class AcompanhamentoItem extends Model
{
    use HasUuids;

    public const STATUS = [
        'a_fazer' => 'A fazer',
        'em_andamento' => 'Em andamento',
        'em_revisao' => 'Em revisão',
        'aprovado' => 'Aprovado',
        'concluido' => 'Concluído',
    ];

    protected $table = 'acompanhamento_itens';

    protected $guarded = [];

    public function acompanhamento(): BelongsTo
    {
        return $this->belongsTo(Acompanhamento::class);
    }

    /** Endereco clicavel: referencia absoluta, ou base_url + referencia quando ela comeca com "/". */
    public function link(): ?string
    {
        $ref = trim((string) $this->referencia);
        if ($ref === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $ref)) {
            return $ref;
        }
        $base = rtrim((string) $this->acompanhamento?->base_url, '/');

        return $base !== '' && str_starts_with($ref, '/') ? $base.$ref : null;
    }
}
