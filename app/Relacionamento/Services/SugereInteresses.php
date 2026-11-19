<?php

namespace App\Relacionamento\Services;

use App\Relacionamento\Models\RelInteresse;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sugere interesses pelo tema dos conteudos que a empresa consumiu (rel_conteudos.tema). So' sugere; quem marca e' a equipe. */
class SugereInteresses
{
    /**
     * @param  HasMany  $interacoes  ex.: $prospect->relInteracoes()
     * @return array<string,int> tema => quantidade de acessos, do mais ao menos consumido
     */
    public function para(HasMany $interacoes): array
    {
        return (clone $interacoes)
            ->join('rel_conteudos', 'rel_conteudos.slug', '=', 'rel_interacoes.conteudo_slug')
            ->whereIn('rel_conteudos.tema', RelInteresse::TEMAS)
            ->reorder()
            ->selectRaw('rel_conteudos.tema as tema, count(*) as n')
            ->groupBy('rel_conteudos.tema')
            ->orderByDesc('n')
            ->pluck('n', 'tema')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
