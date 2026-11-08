<?php

namespace App\Dashboards\Blocks\Planejamento;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\MaintenancePlan;

class PreventivasMaisAtrasadas extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'pmp.mais_atrasadas';
    }

    public static function title(): string
    {
        return 'Preventivas mais atrasadas';
    }

    public static function subtitle(): string
    {
        return 'Onde a preventiva passou mais do limite';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function module(): string
    {
        return 'PMP';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_maintenance_plans';
    }

    public static function sourceModel(): string
    {
        return MaintenancePlan::class;
    }

    public function data(array $config): array
    {
        $vencidas = collect((new PreventivasVencidas)->publicSituacao()[0])->sortByDesc('horas')->take(8)->map(fn ($l) => [
            $l['ativo'], $l['plano'], Payload::pill('crit', $l['horas'] > 0 ? number_format($l['horas'], 0, ',', '.').' h acima' : 'vencida por data/ciclos'),
        ])->values()->all();

        return Payload::table(['Equipamento', 'Item do plano', '>Situação'], $vencidas, 'Nenhuma preventiva vencida.');
    }
}
