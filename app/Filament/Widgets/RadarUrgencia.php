<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;
use App\Models\MaintenanceOrder;
use App\Models\Asset;

class RadarUrgencia extends BaseWidget
{
    /**
     * Impede que o widget tente renderizar se não houver um tenant ativo.
     * Essencial para evitar erros em telas de login ou fora do contexto de tenant.
     */
    public static function canView(): bool
    {
        return (bool) \App\Support\Tenancy::current();
    }

    /**
     * Define as estatísticas exibidas no painel.
     */
    protected function getStats(): array
    {
        $tenant = \App\Support\Tenancy::current();

        // Se por algum motivo o tenant for nulo, retornamos array vazio
        if (!$tenant) {
            return [];
        }

        $tenantId = $tenant->id;

        return [
            Stat::make('Manutenção em Atraso', 
                MaintenanceOrder::where('tenant_id', $tenantId)
                                ->where('status', 'atrasado')
                                ->count()
            )
            ->description('O.S. críticas pendentes')
            ->color('danger'),

            Stat::make('Preventiva Próxima', 
                MaintenanceOrder::where('tenant_id', $tenantId)
                                ->where('status', 'proxima')
                                ->count()
            )
            ->description('Baseado em horímetro')
            ->color('warning'),

            Stat::make('Disponível no Pátio', 
                Asset::where('tenant_id', $tenantId)
                     ->where('status', 'disponivel')
                     ->count()
            )
            ->description('Pronto para despacho')
            ->color('success'),
        ];
    }
}