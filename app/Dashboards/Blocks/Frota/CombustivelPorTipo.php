<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\FrotaAbastecimento;

class CombustivelPorTipo extends FrotaBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'frota.combustivel_por_tipo';
    }

    public static function title(): string
    {
        return 'Combustível por tipo';
    }

    public static function subtitle(): string
    {
        return 'Litros abastecidos no mês, por combustível';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $porTipo = FrotaAbastecimento::query()->whereBetween('abastecido_em', [now()->startOfMonth(), now()->endOfMonth()])->selectRaw('combustivel, sum(litros) as total')->groupBy('combustivel')->pluck('total', 'combustivel');

        return Payload::donut($this->fatias($porTipo->mapWithKeys(fn ($v, $k) => [(string) ($k ?: 'Não informado') => round((float) $v, 1)])), number_format((float) $porTipo->sum(), 0, ',', '.'), 'litros');
    }
}
