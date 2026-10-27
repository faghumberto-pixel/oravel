<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Block;
use App\Models\FrotaAbastecimento;

/** Base dos blocos de Gestão de Frota (combustível, óleo, multas e sinistros dos veículos). */
abstract class FrotaBlock extends Block
{
    public static function module(): string
    {
        return 'Gestão de Frota';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_frota_abastecimentos';
    }

    public static function sourceModel(): string
    {
        return FrotaAbastecimento::class;
    }

    protected function brl(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    protected function gastoComCombustivel($de, $ate): float
    {
        return (float) FrotaAbastecimento::query()->whereBetween('abastecido_em', [$de, $ate])->sum('valor_total');
    }
}
