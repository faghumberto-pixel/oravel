<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class RetornoMaquina extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.retorno_maquina';
    }

    public static function title(): string
    {
        return 'Voltou à máquina';
    }

    public static function subtitle(): string
    {
        return 'Retrabalho nos últimos 30 dias';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $periodo = MaintenanceOrder::query()->where('created_at', '>=', now()->subDays(30))->whereNotIn('status', ['Cancelada']);
        $total = (clone $periodo)->count();
        $retrabalho = (clone $periodo)->where('is_rework', true)->count();
        $pct = $total > 0 ? (int) round($retrabalho / $total * 100) : 0;

        return Payload::kpi($pct.'%', '', $retrabalho.' de '.$this->plural($total, 'atendimento', 'atendimentos'), 'n', ['pct' => $pct, 'tone' => 'warn']);
    }
}
