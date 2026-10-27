<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Block;
use App\Models\AccountReceivable;
use Illuminate\Database\Eloquent\Builder;

/** Base dos blocos de Financeiro. "Em aberto" = qualquer situação diferente de paga; atraso é calculado pelo vencimento. */
abstract class FinanceiroBlock extends Block
{
    public static function module(): string
    {
        return 'Financeiro';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_account_receivables';
    }

    public static function sourceModel(): string
    {
        return AccountReceivable::class;
    }

    protected function brl(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    protected function emAberto(Builder $consulta): Builder
    {
        return $consulta->where('status', '!=', 'pago');
    }

    protected function atrasadas(Builder $consulta): Builder
    {
        return $this->emAberto($consulta)->where('due_date', '<', now()->toDateString());
    }
}
