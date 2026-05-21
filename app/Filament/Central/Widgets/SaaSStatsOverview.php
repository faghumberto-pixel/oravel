<?php

namespace App\Filament\Central\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Tenant;
use App\Models\User;

class SaaSStatsOverview extends BaseWidget
{
    // Esta herança (extends BaseWidget) é o que resolve o erro is_subclass_of na linha 115 do Livewire
    
    protected function getStats(): array
    {
        return [
            Stat::make('Total de Tenants', Tenant::count() ?? 0)
                ->description('Clientes ativos na plataforma')
                ->color('success'),

            Stat::make('Total de Usuários', User::count() ?? 0)
                ->description('Usuários globais')
                ->color('primary'),
        ];
    }
}