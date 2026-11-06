<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\FreightRecord;

class FretePorMes extends LogisticaBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'logistica.frete_por_mes';
    }

    public static function title(): string
    {
        return 'Frete por mês';
    }

    public static function subtitle(): string
    {
        return 'Gasto com frete nos últimos 6 meses, e viagens feitas';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $meses = 6;
        $inicio = now()->startOfMonth()->subMonths($meses - 1);
        $valores = $this->serieMensal(FreightRecord::query(), 'data', $meses, 'valor');
        $viagens = $this->serieMensal(FreightRecord::query(), 'data', $meses);
        $rotulos = collect(range(0, $meses - 1))->map(fn ($i) => ucfirst($inicio->copy()->addMonths($i)->translatedFormat('M')))->all();

        return Payload::cols($rotulos, array_map(fn ($v) => round($v / 1000, 1), $valores), $viagens, 'Frete (R$ mil)', 'Viagens');
    }
}
