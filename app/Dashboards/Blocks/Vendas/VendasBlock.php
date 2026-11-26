<?php

namespace App\Dashboards\Blocks\Vendas;

use App\Dashboards\Block;
use Carbon\Carbon;

/**
 * Base dos blocos de gestão comercial (CRM): metas, propostas, visitas, perdas e leads parados.
 * Lê só tabelas do CRM, por isso funciona tanto no pacote Vendas quanto no Comercial do ERP.
 */
abstract class VendasBlock extends Block
{
    public static function module(): string
    {
        return 'Comercial';
    }

    protected function brl(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    protected function compacto(float $v): string
    {
        $abs = abs($v);

        return match (true) {
            $abs >= 1_000_000 => 'R$ '.number_format($abs / 1_000_000, 1, ',', '.').' mi',
            $abs >= 100_000 => 'R$ '.number_format($abs / 1000, 0, ',', '.').' mil',
            $abs >= 1000 => 'R$ '.number_format($abs / 1000, 1, ',', '.').' mil',
            default => 'R$ '.number_format($abs, 0, ',', '.'),
        };
    }

    protected function mesAtual(): Carbon
    {
        return Carbon::now()->startOfMonth();
    }
}
