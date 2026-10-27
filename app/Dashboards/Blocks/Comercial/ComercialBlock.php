<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Block;
use App\Models\Contract;

/** Base dos blocos de Comercial (contratos, orçamentos, CRM e pedidos de locação). */
abstract class ComercialBlock extends Block
{
    public static function module(): string
    {
        return 'Comercial';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_contracts';
    }

    public static function sourceModel(): string
    {
        return Contract::class;
    }

    protected function brl(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
