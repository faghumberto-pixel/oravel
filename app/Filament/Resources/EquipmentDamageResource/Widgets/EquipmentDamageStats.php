<?php

namespace App\Filament\Resources\EquipmentDamageResource\Widgets;

use App\Models\EquipmentDamage;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EquipmentDamageStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = EquipmentDamage::count();

        $pendentes = EquipmentDamage::whereNotIn('status', [
            EquipmentDamage::STATUS_RESOLVIDO,
            EquipmentDamage::STATUS_CANCELADO,
        ])->count();

        $noMes = EquipmentDamage::where('created_at', '>=', now()->startOfMonth())->count();

        $custoTotal = EquipmentDamage::whereNotIn('status', [EquipmentDamage::STATUS_CANCELADO])
            ->sum('estimated_cost');

        return [
            Stat::make('Total de Avarias', $total)
                ->description('Todas registradas')
                ->color('gray'),

            Stat::make('Em Andamento', $pendentes)
                ->description('Aguardando resolução')
                ->color($pendentes > 0 ? 'warning' : 'success'),

            Stat::make('Registradas no Mês', $noMes)
                ->description(now()->translatedFormat('F/Y'))
                ->color('info'),

            Stat::make('Custo Estimado Total', 'R$ '.number_format((float) $custoTotal, 2, ',', '.'))
                ->description('Reparo/reposição')
                ->color('danger'),
        ];
    }
}
