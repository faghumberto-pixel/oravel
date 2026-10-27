<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class DefeitosFrequentes extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.defeitos_frequentes';
    }

    public static function title(): string
    {
        return 'Defeitos mais frequentes';
    }

    public static function subtitle(): string
    {
        return 'Últimos 90 dias';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $ordens = MaintenanceOrder::query()
            ->with('reportedProblem:id,description')
            ->whereNotNull('reported_problem_id')
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('reported_problem_id, count(*) as total')
            ->groupBy('reported_problem_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return Payload::bars($ordens->map(fn ($o) => [$o->reportedProblem?->description ?? 'Sem descrição', (int) $o->total])->all());
    }
}
