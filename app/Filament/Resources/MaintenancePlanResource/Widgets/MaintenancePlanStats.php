<?php

namespace App\Filament\Resources\MaintenancePlanResource\Widgets;

use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MaintenancePlanStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    /**
     * "Vencido" agora precisa lidar com dois formatos: plano por-Ativo
     * (last_service_hours direto) e template por-Grupo (compartilhado por
     * varios Ativos -- cada combinacao plano+ativo tem seu proprio status,
     * calculado via dueStatusForAsset()). Itera por ATIVO (nao por plano)
     * e usa MaintenancePlan::applicableFor() -- assim um item do grupo que
     * o Ativo ja personalizou (mesma logica de override por nome) conta
     * uma vez so, nao duas.
     */
    protected function getStats(): array
    {
        $planos = MaintenancePlan::with(['asset', 'checklistGroup'])->where('is_active', true)->get();

        $assetsByGroup = Asset::whereIn('checklist_group_id', $planos->pluck('checklist_group_id')->filter()->unique())
            ->get()
            ->groupBy('checklist_group_id');

        $assetsComPlanoProprio = $planos->whereNotNull('asset_id')->pluck('asset')->filter();

        $assetsRelevantes = $assetsByGroup->flatten(1)->merge($assetsComPlanoProprio)->unique('id');

        $vencidos = 0;
        $assetsCobertosIds = collect();

        foreach ($assetsRelevantes as $asset) {
            $planosDoAtivo = MaintenancePlan::applicableFor($asset, $planos);

            if ($planosDoAtivo->isEmpty()) {
                continue;
            }

            $assetsCobertosIds->push($asset->id);
            $vencidos += $planosDoAtivo->filter(fn (MaintenancePlan $plano) => $plano->dueStatusForAsset($asset)['is_overdue'])->count();
        }

        return [
            Stat::make('Total de Itens de Preventiva', $planos->count())
                ->description('Por-ativo + templates de grupo')
                ->color('gray'),

            Stat::make('Ativos', $planos->count())
                ->description('Em monitoramento')
                ->color('info'),

            Stat::make('Vencidos por Horímetro', $vencidos)
                ->description('Item × Ativo que ultrapassou o intervalo previsto')
                ->color($vencidos > 0 ? 'danger' : 'success'),

            Stat::make('Ativos Cobertos', $assetsCobertosIds->unique()->count())
                ->description('Com ao menos 1 item de preventiva aplicável')
                ->color('success'),
        ];
    }
}
