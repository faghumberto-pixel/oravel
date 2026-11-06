<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;

class SaidasPorMes extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.saidas_por_mes';
    }

    public static function title(): string
    {
        return 'Saídas e km por mês';
    }

    public static function subtitle(): string
    {
        return 'Últimos 6 meses';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $meses = 6;
        $inicio = now()->startOfMonth()->subMonths($meses - 1);
        $saidas = FrotaSaidaVeiculo::query()->where('saida_em', '>=', $inicio)->get();
        $porMes = $saidas->groupBy(fn ($s) => $s->saida_em->format('Y-m'));
        $rotulos = [];
        $qtd = [];
        $km = [];
        for ($i = 0; $i < $meses; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $g = $porMes[$mes->format('Y-m')] ?? collect();
            $rotulos[] = ucfirst($mes->translatedFormat('M'));
            $qtd[] = $g->count();
            $km[] = round($g->sum(fn ($s) => $this->kmDaSaida($s)) / 100, 1);
        }

        return Payload::cols($rotulos, $qtd, $km, 'Saídas', 'Km (centenas)');
    }
}
