<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaAbastecimento;

class ConsumoMedio extends FrotaBlock
{
    public static function key(): string
    {
        return 'frota.consumo_medio';
    }

    public static function title(): string
    {
        return 'Consumo médio';
    }

    public static function subtitle(): string
    {
        return 'Quilômetros por litro, no mês';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $mes = FrotaAbastecimento::query()->whereBetween('abastecido_em', [now()->startOfMonth(), now()->endOfMonth()]);
        $media = (clone $mes)->whereNotNull('consumo_km_l')->where('consumo_km_l', '>', 0)->avg('consumo_km_l');
        $litros = (float) (clone $mes)->sum('litros');

        if ($media === null) {
            return Payload::kpi('—', '', 'Sem abastecimento com consumo calculado');
        }

        return Payload::kpi(number_format((float) $media, 1, ',', '.'), 'km/l', number_format($litros, 0, ',', '.').' litros abastecidos');
    }
}
