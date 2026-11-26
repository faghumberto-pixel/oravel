<?php

namespace App\Dashboards\Blocks\Vendas;

use App\Dashboards\Payload;
use App\Models\CrmMeta;
use App\Models\CrmVisita;

class VisitasDoMes extends VendasBlock
{
    public static function key(): string
    {
        return 'vendas.visitas_mes';
    }

    public static function title(): string
    {
        return 'Visitas do mês';
    }

    public static function subtitle(): string
    {
        return 'Realizadas contra a meta';
    }

    public static function size(): int
    {
        return 4;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_crm_visitas';
    }

    public static function sourceModel(): string
    {
        return CrmVisita::class;
    }

    public function data(array $config): array
    {
        $mes = $this->mesAtual();
        $feitas = CrmVisita::query()->where('status', CrmVisita::STATUS_REALIZADA)->where('agendada_para', '>=', $mes)->count();
        $agendadas = CrmVisita::query()->where('status', CrmVisita::STATUS_AGENDADA)->where('agendada_para', '>=', now())->count();
        $meta = (int) CrmMeta::query()->whereDate('mes', $mes->toDateString())->whereNotNull('user_id')->sum('meta_visitas');

        $porDia = CrmVisita::query()->where('status', CrmVisita::STATUS_REALIZADA)->where('agendada_para', '>=', now()->subDays(13)->startOfDay())->get(['agendada_para'])
            ->groupBy(fn ($v) => $v->agendada_para->format('Y-m-d'))->map->count();
        $serie = collect(range(13, 0))->map(fn ($i) => (int) ($porDia[now()->subDays($i)->format('Y-m-d')] ?? 0))->all();

        $detalhe = $agendadas.' agendadas pela frente'.($meta > 0 ? ' · meta '.$meta : '');
        $bar = $meta > 0 ? ['pct' => min(100, (int) round($feitas / $meta * 100))] : null;

        return Payload::kpi($feitas, 'realizadas', $detalhe, $meta > 0 && $feitas < $meta * now()->day / now()->daysInMonth ? 'dn' : 'up', $bar, $serie);
    }
}
