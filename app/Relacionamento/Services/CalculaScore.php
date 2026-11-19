<?php

namespace App\Relacionamento\Services;

use App\Relacionamento\Models\RelScoreRegra;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Score de relacionamento (0 a 100) de uma empresa. Calculado na hora a partir de rel_interacoes
 * (nunca gravado como verdade): so' a janela de dias da config, e cada tipo conta no maximo `limite`
 * vezes, para um unico acesso repetido nao inflar a nota. Pesos editaveis em rel_score_regras.
 */
class CalculaScore
{
    /** @var array<string,array{pontos:int,limite:int}>|null */
    private ?array $regras = null;

    /**
     * @param  HasMany  $interacoes  ex.: $prospect->relInteracoes()
     * @return array{score:int,faixa:string,cor:string,detalhe:array<string,int>}
     */
    public function calcular(HasMany $interacoes): array
    {
        $contagens = (clone $interacoes)
            ->where('ocorrido_em', '>=', now()->subDays((int) config('relacionamento.janela_dias', 90)))
            ->reorder()
            ->selectRaw('tipo, count(*) as n')
            ->groupBy('tipo')
            ->pluck('n', 'tipo');

        $detalhe = [];
        foreach ($this->regras() as $tipo => $r) {
            $n = min((int) ($contagens[$tipo] ?? 0), $r['limite']);
            if ($n > 0 && $r['pontos'] !== 0) {
                $detalhe[$tipo] = $n * $r['pontos'];
            }
        }

        $score = max(0, min(100, array_sum($detalhe)));
        [$faixa, $cor] = $this->faixa($score);

        return ['score' => $score, 'faixa' => $faixa, 'cor' => $cor, 'detalhe' => $detalhe];
    }

    /** @return array{0:string,1:string} [rotulo, cor] */
    public function faixa(int $score): array
    {
        foreach ((array) config('relacionamento.faixas') as $f) {
            if ($score >= $f['min']) {
                return [$f['rotulo'], $f['cor']];
            }
        }

        return ['Baixo', 'gray'];
    }

    private function regras(): array
    {
        if ($this->regras !== null) {
            return $this->regras;
        }

        $linhas = RelScoreRegra::where('ativo', true)->get();
        if ($linhas->isEmpty() && ! RelScoreRegra::exists()) {
            return $this->regras = collect(RelScoreRegra::PADRAO)->map(fn ($p) => ['pontos' => $p[0], 'limite' => $p[1]])->all();
        }

        return $this->regras = $linhas->mapWithKeys(fn ($r) => [$r->tipo => ['pontos' => $r->pontos, 'limite' => $r->limite]])->all();
    }
}
