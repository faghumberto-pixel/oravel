<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\FrotaAbastecimento;

class GastoComCombustivel extends FrotaBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'frota.combustivel_mes';
    }

    public static function title(): string
    {
        return 'Combustível no mês';
    }

    public static function subtitle(): string
    {
        return 'Abastecimentos lançados';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $atual = $this->gastoComCombustivel(now()->startOfMonth(), now()->endOfMonth());
        $anterior = $this->gastoComCombustivel(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());

        $serie = $this->serieMensal(FrotaAbastecimento::query(), 'abastecido_em', 12, 'valor_total');
        if ($anterior <= 0) {
            return Payload::kpi($this->brl($atual), '', 'Sem mês anterior para comparar', 'n', null, $serie);
        }

        $pct = round(($atual - $anterior) / $anterior * 100);

        // gastar menos que no mês anterior é a variação boa
        return Payload::kpi($this->brl($atual), '', ($pct >= 0 ? '+' : '').$pct.'% sobre o mês anterior', $pct <= 0 ? 'up' : 'dn', null, $serie);
    }
}
