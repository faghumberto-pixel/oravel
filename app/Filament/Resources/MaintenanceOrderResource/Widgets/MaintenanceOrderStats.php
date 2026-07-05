<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Widgets;

use App\Filament\Attributes\BelongsToFeature;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

#[BelongsToFeature('maintenance')]
class MaintenanceOrderStats extends BaseWidget
{
    /**
     * Define se o widget deve ser exibido.
     */
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $tenant = Tenancy::current();

        // Retorno de segurança caso o tenant não esteja definido
        if (! $tenant) {
            return [
                Stat::make('Ativos Liberados', '0 / 0')->color('gray'),
                Stat::make('Trabalhos Feitos', 0)->color('gray'),
                Stat::make('Manutenções em Aberto', 0)->color('gray'),
                Stat::make('OS em Atraso', 0)->color('gray'),
            ];
        }

        $tenantId = $tenant->id;

        $totalAssets = Asset::where('tenant_id', $tenantId)->count();
        $released = Asset::where('tenant_id', $tenantId)->where('status', 'disponivel')->count();
        $done = MaintenanceOrder::where('tenant_id', $tenantId)->whereIn('status', ['Concluída', 'Completado'])->count();

        $open = MaintenanceOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['Aberto', 'Em Andamento', 'Pendente', 'Reprogramado'])
            ->count();

        $overdue = MaintenanceOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['Aberto', 'Pendente', 'Em Andamento'])
            ->where('created_at', '<', now()->subDays(3))
            ->count();

        return [
            Stat::make('Ativos Liberados', "{$released} / {$totalAssets}")
                ->description('Disponibilidade da Frota')
                ->color('success'),

            Stat::make('Trabalhos Feitos', $done)
                ->description('OS Finalizadas')
                ->color('info'),

            Stat::make('Manutenções em Aberto', $open)
                ->description('OS em fila ou execução')
                ->color('warning'),

            Stat::make('OS em Atraso', $overdue)
                ->description('Abertas há mais de 3 dias')
                ->color($overdue > 0 ? 'danger' : 'success'),
        ];
    }
}
