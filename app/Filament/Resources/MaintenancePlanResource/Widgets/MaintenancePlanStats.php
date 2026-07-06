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
     * calculado via dueStatusForAsset()).
     */
    protected function getStats(): array
    {
        $planos = MaintenancePlan::with(['asset', 'checklistGroup'])->where('is_active', true)->get();

        $assetsByGroup = Asset::whereIn('checklist_group_id', $planos->pluck('checklist_group_id')->filter()->unique())
            ->get()
            ->groupBy('checklist_group_id');

        $vencidos = 0;
        $assetsCobertosIds = collect();

        foreach ($planos as $plan) {
            if ($plan->isGroupTemplate()) {
                $assetsDoGrupo = $assetsByGroup->get($plan->checklist_group_id, collect());

                foreach ($assetsDoGrupo as $asset) {
                    $assetsCobertosIds->push($asset->id);

                    if ($plan->dueStatusForAsset($asset)['is_overdue']) {
                        $vencidos++;
                    }
                }
            } elseif ($plan->asset) {
                $assetsCobertosIds->push($plan->asset_id);

                if ($plan->dueStatusForAsset($plan->asset)['is_overdue']) {
                    $vencidos++;
                }
            }
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
