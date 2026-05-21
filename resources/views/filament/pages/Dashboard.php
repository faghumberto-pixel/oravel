<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\PcmStatsOverview;
use App\Filament\Widgets\AssetStatusChart;
use App\Filament\Widgets\MaintenanceTrendsChart;
use App\Filament\Widgets\AssetDistributionChart;
use App\Filament\Widgets\AssetMapWidget;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static string $view = 'filament.pages.painel-gestao'; 
    
    protected static ?string $navigationLabel = 'Painel de Controle';
    protected static ?string $title = 'Painel de Controle';
    protected static ?string $slug = 'dashboard';

    /**
     * Trava de Segurança Suprema: Remove o painel da barra lateral do Bruno reativamente.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if ($user->hasRole('gestor')) {
            return true;
        }

        return false;
    }

    /**
     * Retorna os componentes injetados na Grid de 3 colunas.
     */
    public function getWidgets(): array
    {
        return [
            StatsOverview::class,           
            PcmStatsOverview::class,        
            AssetStatusChart::class,        
            MaintenanceTrendsChart::class,  
            AssetDistributionChart::class,  
            AssetMapWidget::class,          
        ];
    }

    public function getColumns(): int | string | array
    {
        return 3;
    }
}