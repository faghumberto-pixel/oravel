<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TechnicianOrderStats extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $myId = Auth::id();

        $base = MaintenanceOrder::query()->where('technician_id', $myId);

        $abertas    = (clone $base)->where('status', 'Aberto')->count();
        $pendentes  = (clone $base)->whereIn('status', ['Pendente', 'Em Andamento'])->count();
        $executadas = (clone $base)->whereIn('status', ['Concluída', 'Completado'])->count();

        return [
            Stat::make('Abertas', $abertas)
                ->description('OS atribuídas a você, em aberto')
                ->descriptionIcon('heroicon-o-inbox')
                ->color('warning'),

            Stat::make('Pendentes', $pendentes)
                ->description('Aguardando ou em andamento')
                ->descriptionIcon('heroicon-o-clock')
                ->color('info'),

            Stat::make('Executadas', $executadas)
                ->description('Concluídas por você')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
