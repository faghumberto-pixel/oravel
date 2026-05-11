<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\Contract;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationalAlerts extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $tenantId = auth()->user()->tenant_id;

        if (!$tenantId) return [];

        // 1. Equipamentos na Oficina
        $naOficina = Asset::where('tenant_id', $tenantId)
            ->where('status', 'Manutenção')
            ->count();

        // 2. Devoluções Hoje
        $devolucoesHoje = Contract::where('tenant_id', $tenantId)
            ->where('status', 'Ativo')
            ->whereDate('end_date', now())
            ->count();

        // 3. Atrasos de Devolução
        $atrasados = Contract::where('tenant_id', $tenantId)
            ->where('status', 'Ativo')
            ->where('end_date', '<', now())
            ->count();

        return [
            Stat::make('Na Oficina', $naOficina)
                ->description('Bloqueados para locação')
                ->descriptionIcon('heroicon-m-wrench')
                ->color($naOficina > 0 ? 'danger' : 'gray'),

            Stat::make('Devoluções Hoje', $devolucoesHoje)
                ->description('Contratos vencendo hoje')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),

            Stat::make('Contratos Atrasados', $atrasados)
                ->description('Inadimplência de prazo')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($atrasados > 0 ? 'danger' : 'success'),
        ];
    }
}