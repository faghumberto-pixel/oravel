<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaAbastecimento;

class CustoPorKm extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.custo_por_km';
    }

    public static function title(): string
    {
        return 'Custo de combustível por km';
    }

    public static function subtitle(): string
    {
        return 'Últimos 30 dias, pelos abastecimentos';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $a = FrotaAbastecimento::query()->where('abastecido_em', '>=', now()->subDays(30))->where('km_rodado', '>', 0);
        $km = (float) (clone $a)->sum('km_rodado');
        if ($km <= 0) {
            return Payload::kpi('—', '', 'Sem abastecimento com km informado');
        }
        $valor = (float) (clone $a)->sum('valor_total');
        $anterior = FrotaAbastecimento::query()->whereBetween('abastecido_em', [now()->subDays(60), now()->subDays(30)])->where('km_rodado', '>', 0);
        $kmAnt = (float) (clone $anterior)->sum('km_rodado');
        $custo = $valor / $km;
        $delta = 'R$ '.number_format($valor, 2, ',', '.').' em '.number_format($km, 0, ',', '.').' km';
        if ($kmAnt > 0) {
            $dif = $custo - (float) (clone $anterior)->sum('valor_total') / $kmAnt;
            $delta .= ' · '.($dif <= 0 ? '' : '+').number_format($dif, 2, ',', '.').' sobre os 30 dias anteriores';
        }

        return Payload::kpi('R$ '.number_format($custo, 2, ',', '.'), '/km', $delta, isset($dif) ? ($dif <= 0 ? 'up' : 'dn') : 'n');
    }
}
