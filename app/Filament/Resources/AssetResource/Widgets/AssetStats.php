<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Pedido do usuário 2026-08-30: retirar os gráficos da tela de Ativos,
 * deixar só cards -- Total de Ativos + Ativos sem PMP (nenhum plano
 * preventivo aplicável, nem próprio nem herdado do Grupo, ver
 * MaintenancePlan::applicableFor()).
 */
class AssetStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $assets = Asset::with('checklistGroup')->get();
        $planos = MaintenancePlan::all();

        $semPmp = $assets->filter(
            fn (Asset $asset) => MaintenancePlan::applicableFor($asset, $planos)->isEmpty()
        )->count();

        return [
            Stat::make('Total de Ativos', $assets->count())
                ->description('Cadastrados na frota')
                ->color('gray'),

            Stat::make('Ativos sem PMP', $semPmp)
                ->description('Sem plano preventivo aplicável')
                ->color($semPmp > 0 ? 'danger' : 'success'),
        ];
    }
}
