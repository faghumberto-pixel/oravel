<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\PcmStatsOverview;
use App\Filament\Widgets\AssetStatusChart;
use App\Filament\Widgets\MaintenanceTrendsChart;
use App\Filament\Widgets\AssetDistributionChart;
use App\Filament\Widgets\AssetMapWidget;

class PainelGestao extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static string $view = 'filament.pages.dashboard-custom';
    protected static ?string $navigationLabel = 'Painel de Controle';
    protected static ?string $title = 'Painel de Controle';
    protected static ?string $slug = 'dashboard';

    public function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,           // Resumo da Operação (Ocupa linha toda)
            PcmStatsOverview::class,        // Saúde da Frota (PCM) (Ocupa linha toda)
            AssetStatusChart::class,        // Status Donut (Vai ocupar 1 coluna)
            MaintenanceTrendsChart::class,  // Tendências de Custos (Vai ocupar 2 colunas)
            AssetDistributionChart::class,  // Faturamento vs Custos (Ocupa linha toda)
            AssetMapWidget::class,          // Mapa de Ativos (Ocupa linha toda)
        ];
    }

    // Define a grade da página para 3 colunas
    public function getColumns(): int | string | array
    {
        return 3;
    }

    // Garante que os widgets do cabeçalho também respeitem as 3 colunas
    public function getHeaderWidgetsColumns(): int | array
    {
        return 3;
    }
}