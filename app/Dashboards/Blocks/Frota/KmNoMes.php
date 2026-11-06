<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;

class KmNoMes extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.km_no_mes';
    }

    public static function title(): string
    {
        return 'Km rodados no mês';
    }

    public static function subtitle(): string
    {
        return 'Pelas saídas concluídas (odômetro de saída e retorno)';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $saidas = FrotaSaidaVeiculo::query()->whereNotNull('retorno_em')->where('saida_em', '>=', now()->startOfMonth())->get();
        $km = $saidas->sum(fn ($s) => $this->kmDaSaida($s));
        $inicio = now()->startOfMonth()->subMonths(5);
        $porMes = FrotaSaidaVeiculo::query()->whereNotNull('retorno_em')->where('saida_em', '>=', $inicio)->get()->groupBy(fn ($s) => $s->saida_em->format('Y-m'))->map(fn ($g) => $g->sum(fn ($s) => $this->kmDaSaida($s)));
        $serie = collect(range(0, 5))->map(fn ($i) => (int) ($porMes[$inicio->copy()->addMonths($i)->format('Y-m')] ?? 0))->all();

        return Payload::kpi(number_format($km, 0, ',', '.'), 'km', $saidas->count().' saídas concluídas neste mês', 'n', null, $serie);
    }
}
