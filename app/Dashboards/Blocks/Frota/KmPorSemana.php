<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;

class KmPorSemana extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.km_por_semana';
    }

    public static function title(): string
    {
        return 'Km rodados por semana';
    }

    public static function subtitle(): string
    {
        return 'Últimas 8 semanas, pelas saídas concluídas';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $semanas = 8;
        $inicio = now()->startOfWeek()->subWeeks($semanas - 1);
        $saidas = FrotaSaidaVeiculo::query()->whereNotNull('retorno_em')->where('saida_em', '>=', $inicio)->get();
        $rotulos = [];
        $valores = [];
        for ($i = 0; $i < $semanas; $i++) {
            $de = $inicio->copy()->addWeeks($i);
            $rotulos[] = $de->format('d/m');
            $valores[] = $saidas->filter(fn ($s) => $s->saida_em->between($de, $de->copy()->endOfWeek()))->sum(fn ($s) => $this->kmDaSaida($s));
        }

        return Payload::line($rotulos, $valores);
    }
}
