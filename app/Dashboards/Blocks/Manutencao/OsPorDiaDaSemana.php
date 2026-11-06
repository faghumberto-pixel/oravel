<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class OsPorDiaDaSemana extends ManutencaoBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'manutencao.mapa_calor_semana';
    }

    public static function title(): string
    {
        return 'Quando as OS acontecem';
    }

    public static function subtitle(): string
    {
        return 'Mapa de calor por dia da semana e tipo, últimos 60 dias';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $ordens = MaintenanceOrder::query()->where('created_at', '>=', now()->subDays(60))->whereNotIn('status', ['Cancelada'])->get(['maintenance_type', 'created_at']);
        $dias = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
        $tipos = $ordens->groupBy(fn ($o) => $o->maintenance_type ?: 'Sem tipo')->map->count()->sortDesc()->take(5)->keys();

        $linhas = $tipos->map(function ($tipo) use ($ordens) {
            $valores = [];
            for ($d = 1; $d <= 7; $d++) {
                $valores[] = $ordens->filter(fn ($o) => ($o->maintenance_type ?: 'Sem tipo') === $tipo && $o->created_at->dayOfWeekIso === $d)->count();
            }

            return [$tipo, '', $valores, array_sum($valores).' OS', null];
        })->all();

        return Payload::heat($dias, $linhas, '', 'Mais OS no dia');
    }
}
