<?php

namespace App\Filament\Resources\MaintenancePlanResource\Support;

use App\Models\Asset;
use App\Models\MaintenancePlan;

/**
 * Status de vencimento por PLANO (linha da tabela de Planos Preventivos),
 * diferente de CoberturaPmp::statusFor() que calcula por ATIVO. Um plano
 * por-Ativo tem um único Ativo a considerar; um plano de Grupo (template,
 * checklist_group_id preenchido) é compartilhado por vários Ativos do
 * grupo, então o status do plano é o PIOR caso entre eles (vencido >
 * a_vencer > dentro_do_prazo) -- reaproveita dueStatusForAsset() e
 * projectedDueDates() já existentes em MaintenancePlan, sem cálculo novo.
 */
class PlanStatus
{
    public static function forPlan(MaintenancePlan $plan): string
    {
        if ($plan->isGroupTemplate()) {
            $assets = Asset::where('checklist_group_id', $plan->checklist_group_id)->get();

            if ($assets->isEmpty()) {
                return 'dentro_do_prazo';
            }

            $statuses = $assets->map(fn (Asset $asset) => static::forAsset($plan, $asset));

            if ($statuses->contains('vencido')) {
                return 'vencido';
            }

            return $statuses->contains('a_vencer') ? 'a_vencer' : 'dentro_do_prazo';
        }

        if (! $plan->asset) {
            return 'dentro_do_prazo';
        }

        return static::forAsset($plan, $plan->asset);
    }

    private static function forAsset(MaintenancePlan $plan, Asset $asset): string
    {
        if ($plan->dueStatusForAsset($asset)['is_overdue']) {
            return 'vencido';
        }

        $vencendoEsteMes = collect($plan->projectedDueDates($asset, 0))->isNotEmpty();

        return $vencendoEsteMes ? 'a_vencer' : 'dentro_do_prazo';
    }

    public static function color(string $status): string
    {
        return match ($status) {
            'vencido' => 'danger',
            'a_vencer' => 'warning',
            default => 'success',
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'vencido' => 'Vencido',
            'a_vencer' => 'A Vencer',
            default => 'Dentro do Prazo',
        };
    }
}
