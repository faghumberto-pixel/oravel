<?php

namespace App\Dashboards\Blocks\Planejamento;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\MaintenancePlan;

class PreventivasAVencer extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'pmp.a_vencer';
    }

    public static function title(): string
    {
        return 'Preventivas a vencer';
    }

    public static function subtitle(): string
    {
        return 'Faltam 50 h ou menos para o limite';
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
        $proximas = collect((new PreventivasVencidas)->publicSituacao()[1])->sortBy('horas')->take(8)->map(fn ($l) => [
            $l['ativo'], $l['plano'], Payload::pill($l['horas'] <= 20 ? 'warn' : 'ok', 'faltam '.number_format($l['horas'], 0, ',', '.').' h'),
        ])->values()->all();

        return Payload::table(['Equipamento', 'Item do plano', '>Situação'], $proximas, 'Nada vence nas próximas horas.');
    }
}
