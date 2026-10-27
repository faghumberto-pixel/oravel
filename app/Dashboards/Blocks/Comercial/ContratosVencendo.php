<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\Contract;

class ContratosVencendo extends ComercialBlock
{
    public static function key(): string
    {
        return 'comercial.contratos_vencendo';
    }

    public static function title(): string
    {
        return 'Contratos vencendo';
    }

    public static function subtitle(): string
    {
        return 'Próximos 30 dias';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $vencem = fn (int $dias) => Contract::query()
            ->where('status', 'Ativo')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays($dias)->toDateString()])
            ->count();

        $em30 = $vencem(30);
        $em90 = $vencem(90);

        return Payload::kpi($em30, $em30 === 1 ? 'contrato' : 'contratos', $em90.' em 90 dias', $em30 > 0 ? 'dn' : 'n');
    }
}
