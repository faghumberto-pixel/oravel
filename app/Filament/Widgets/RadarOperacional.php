<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Asset;

class RadarOperacional extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $tenant = \App\Support\Tenancy::current();

        if (! $tenant) {
            return [
                Stat::make('Disponível', 0),
                Stat::make('Locado', 0),
                Stat::make('Em Operação', 0),
                Stat::make('Em Manutenção', 0),
            ];
        }

        // Vocabulario oficial do formulario (AssetResource):
        // disponivel | locado | operando | manutencao
        // Asset ja e isolado por tenant via global scope.
        $disponivel   = Asset::where('status', 'disponivel')->count();
        $locado       = Asset::where('status', 'locado')->count();
        $operando     = Asset::where('status', 'operando')->count();
        $emManutencao = Asset::where('status', 'manutencao')->count();

        return [
            Stat::make('Disponível', $disponivel)
                ->description('Prontos para locação')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Locado', $locado)
                ->description('Em contrato com cliente')
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('info'),

            Stat::make('Em Operação', $operando)
                ->description('Equipamentos em uso')
                ->descriptionIcon('heroicon-o-bolt')
                ->color('warning'),

            Stat::make('Em Manutenção', $emManutencao)
                ->description('Parados para manutenção')
                ->descriptionIcon('heroicon-o-wrench-screwdriver')
                ->color('danger'),
        ];
    }
}
