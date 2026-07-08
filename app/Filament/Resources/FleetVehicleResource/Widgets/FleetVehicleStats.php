<?php

namespace App\Filament\Resources\FleetVehicleResource\Widgets;

use App\Models\FleetVehicle;
use App\Models\FleetVehicleDocument;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FleetVehicleStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = FleetVehicle::count();
        $disponiveis = FleetVehicle::where('status', FleetVehicle::STATUS_DISPONIVEL)->count();
        $emRota = FleetVehicle::where('status', FleetVehicle::STATUS_EM_ROTA)->count();
        $manutencao = FleetVehicle::where('status', FleetVehicle::STATUS_MANUTENCAO)->count();

        $documentosVencendo = FleetVehicleDocument::whereHas('fleetVehicle')
            ->whereNotNull('data_vencimento')
            ->whereBetween('data_vencimento', [now(), now()->addDays(30)])
            ->count();

        return [
            Stat::make('Total de Veículos', $total)
                ->description('Frota própria')
                ->color('gray'),

            Stat::make('Disponíveis', $disponiveis)
                ->description('Prontos para uso')
                ->color('success'),

            Stat::make('Em Rota', $emRota)
                ->description('Em movimentação agora')
                ->color($emRota > 0 ? 'info' : 'gray'),

            Stat::make('Em Manutenção', $manutencao)
                ->description('Parados para reparo')
                ->color($manutencao > 0 ? 'danger' : 'success'),

            Stat::make('Documentos Vencendo', $documentosVencendo)
                ->description('Próximos 30 dias')
                ->color($documentosVencendo > 0 ? 'warning' : 'success'),
        ];
    }
}
