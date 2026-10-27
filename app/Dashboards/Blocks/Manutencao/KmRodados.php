<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class KmRodados extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.km_rodados';
    }

    public static function title(): string
    {
        return 'Quilômetros rodados';
    }

    public static function subtitle(): string
    {
        return 'Deslocamento dos atendimentos no mês';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $mes = MaintenanceOrder::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->where('km_traveled', '>', 0);

        $km = (float) (clone $mes)->sum('km_traveled');
        $atendimentos = (clone $mes)->count();
        $custo = (float) (clone $mes)->sum('logistics_cost');

        $delta = ($atendimentos > 0 && $custo > 0) ? $this->brl($custo / $atendimentos).' por atendimento em deslocamento' : 'Sem custo de deslocamento lançado';

        return Payload::kpi(number_format($km, 0, ',', '.'), 'km', $delta);
    }
}
