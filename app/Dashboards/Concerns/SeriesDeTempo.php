<?php

namespace App\Dashboards\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Series curtas para minigraficos (sparkline) dos KPIs. */
trait SeriesDeTempo
{
    /**
     * Contagem (ou soma de $soma) por mes, dos ultimos $meses meses, pela data de $coluna.
     *
     * @return list<int|float>
     */
    protected function serieMensal(Builder $consulta, string $coluna, int $meses = 12, ?string $soma = null): array
    {
        $inicio = Carbon::now()->startOfMonth()->subMonths($meses - 1);
        $campos = $soma ? [$coluna, $soma] : [$coluna];
        $por = $consulta->where($coluna, '>=', $inicio)->get($campos)->groupBy(fn ($m) => Carbon::parse($m->{$coluna})->format('Y-m'));

        return collect(range(0, $meses - 1))->map(function ($i) use ($por, $inicio, $soma) {
            $g = $por[$inicio->copy()->addMonths($i)->format('Y-m')] ?? collect();

            return $soma ? round((float) $g->sum($soma), 2) : $g->count();
        })->all();
    }

    /** Cores fixas do sistema para fatias de anel. @return list<string> */
    protected function paleta(): array
    {
        return ['#38bdf8', '#16a34a', '#f59e0b', '#a78bfa', '#ef4444', '#f472b6', '#94a3b8'];
    }

    /** @param  iterable<string, int|float>  $valores rotulo => valor */
    protected function fatias(iterable $valores, array $cores = []): array
    {
        $paleta = $this->paleta();
        $i = 0;
        $saida = [];
        foreach ($valores as $rotulo => $valor) {
            if ($valor > 0) {
                $saida[] = [(string) $rotulo, $valor, $cores[$rotulo] ?? $paleta[$i % count($paleta)]];
                $i++;
            }
        }

        return $saida;
    }

    /** Ranking com barra proporcional ao maior valor. @param  iterable<string, int|float>  $valores */
    protected function linhasDeRanking(iterable $valores, callable $texto, string $tom = 'a'): array
    {
        $valores = collect($valores);
        $max = max(1, (float) $valores->max());

        return $valores->map(fn ($v, $rotulo) => [(string) $rotulo, '', (int) round($v / $max * 100), $texto($v), $tom])->values()->all();
    }
}
