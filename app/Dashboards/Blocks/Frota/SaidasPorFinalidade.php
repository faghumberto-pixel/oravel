<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;

class SaidasPorFinalidade extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.saidas_finalidade';
    }

    public static function title(): string
    {
        return 'Para que a frota sai';
    }

    public static function subtitle(): string
    {
        return 'Saídas dos últimos 60 dias por finalidade';
    }

    public static function size(): int
    {
        return 4;
    }

    public function data(array $config): array
    {
        $porFinalidade = FrotaSaidaVeiculo::query()->where('saida_em', '>=', now()->subDays(60))->selectRaw('finalidade, count(*) as total')->groupBy('finalidade')->pluck('total', 'finalidade');
        $rotulos = $this->finalidades();

        return Payload::donut($this->fatias($porFinalidade->mapWithKeys(fn ($n, $f) => [($rotulos[$f] ?? $f) => (int) $n])), (string) $porFinalidade->sum(), 'saídas em 60 dias');
    }
}
