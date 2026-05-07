<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Widgets;

use App\Models\MaintenanceOrder;
use App\Models\Asset;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class MaintenanceOrderStats extends BaseWidget
{
    protected function getStats(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $totalAssets = Asset::where('tenant_id', $tenantId)->count();
        $released = Asset::where('tenant_id', $tenantId)->where('status', 'disponivel')->count();
        $done = MaintenanceOrder::where('tenant_id', $tenantId)->whereIn('status', ['Concluída', 'Completado'])->count();
        
        // Pega exatamente da coluna Status
        $open = MaintenanceOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['Aberto', 'Em Andamento', 'Pendente', 'Reprogramado'])
            ->count();

        $totalOs = MaintenanceOrder::where('tenant_id', $tenantId)->count();
        $rework = MaintenanceOrder::where('tenant_id', $tenantId)->where('is_rework', true)->count();
        $rate = $totalOs > 0 ? ($rework / $totalOs) * 100 : 0;

        return [
            Stat::make('Ativos Liberados', "{$released} / {$totalAssets}")
                ->description('Disponibilidade da Frota')->color('success'),
            Stat::make('Trabalhos Feitos', $done)
                ->description('OS Finalizadas')->color('info'),
            Stat::make('Manutenções em Aberto', $open)
                ->description('OS em fila ou execução')->color('warning'),
            Stat::make('Taxa de Retrabalho', number_format($rate, 1) . '%')
                ->description('Qualidade PCM')->color($rate > 10 ? 'danger' : 'success'),
        ];
    }
}
