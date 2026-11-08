<?php

namespace App\Dashboards\Blocks\Planejamento;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Asset;
use App\Models\MaintenancePlan;

class CoberturaDePlanos extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'pmp.cobertura';
    }

    public static function title(): string
    {
        return 'Ativos com plano de preventiva';
    }

    public static function subtitle(): string
    {
        return 'Quantos equipamentos têm pelo menos um item de plano ativo';
    }

    public static function size(): int
    {
        return 3;
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
        $ativos = Asset::query()->limit(500)->get();
        $com = $ativos->filter(fn (Asset $a) => MaintenancePlan::applicableFor($a)->where('is_active', true)->isNotEmpty())->count();
        $total = $ativos->count();
        $pct = $total > 0 ? (int) round($com / $total * 100) : 0;

        return Payload::kpi($pct, '%', $com.' de '.$total.' equipamentos', $pct >= 80 ? 'up' : ($pct >= 50 ? 'n' : 'dn'), ['pct' => $pct]);
    }
}
