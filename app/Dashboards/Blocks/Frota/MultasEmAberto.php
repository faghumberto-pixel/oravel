<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaMulta;

class MultasEmAberto extends FrotaBlock
{
    public static function key(): string
    {
        return 'frota.multas_em_aberto';
    }

    public static function title(): string
    {
        return 'Multas em aberto';
    }

    public static function subtitle(): string
    {
        return 'Ainda não pagas nem canceladas';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_frota_multas';
    }

    public static function sourceModel(): string
    {
        return FrotaMulta::class;
    }

    public function data(array $config): array
    {
        $abertas = FrotaMulta::query()->whereNotIn('situacao', [FrotaMulta::PAGA, FrotaMulta::CANCELADA]);
        $n = (clone $abertas)->count();
        $vencendo = (clone $abertas)->whereBetween('vencimento', [now()->toDateString(), now()->addDays(FrotaMulta::AVISO_VENCIMENTO_DIAS)->toDateString()])->count();

        return Payload::kpi(
            $n,
            '',
            $this->brl((float) (clone $abertas)->sum('valor')).($vencendo > 0 ? ' · '.$vencendo.' vencem em '.FrotaMulta::AVISO_VENCIMENTO_DIAS.' dias' : ''),
            $vencendo > 0 ? 'dn' : 'n',
        );
    }
}
