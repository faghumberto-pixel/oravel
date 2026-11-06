<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class AtendimentosHoje extends ManutencaoBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'manutencao.atendimentos_hoje';
    }

    public static function title(): string
    {
        return 'Atendimentos hoje';
    }

    public static function subtitle(): string
    {
        return 'Agendados para o dia';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $porTipo = MaintenanceOrder::query()
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereNotIn('status', ['Cancelada'])
            ->selectRaw('maintenance_type, count(*) as total')
            ->groupBy('maintenance_type')
            ->pluck('total', 'maintenance_type');

        $total = (int) $porTipo->sum();
        $detalhe = $porTipo->map(fn ($n, $tipo) => $n.' '.mb_strtolower($tipo ?: 'sem tipo'))->implode(', ');

        return Payload::kpi($total, '', $detalhe ?: 'Nenhum atendimento agendado', 'n', null, $this->serieMensal(MaintenanceOrder::query()->whereNotIn('status', ['Cancelada']), 'scheduled_at', 6));
    }
}
