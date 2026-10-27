<?php

namespace App\Dashboards;

use Filament\Pages\Page;

/**
 * Base das páginas "Visão geral" de cada módulo. A subclasse só diz qual dashboard mostrar
 * (ModuleDashboards) e em que grupo do menu fica; o resto é igual para todos os módulos.
 */
abstract class ModuleDashboardPage extends Page
{
    protected static string $view = 'filament.pages.module-dashboard';

    protected static string $dashboardKey;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?int $navigationSort = -10;

    public static function canAccess(): bool
    {
        return DashboardRenderer::available(static::$dashboardKey, auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return ModuleDashboards::find(static::$dashboardKey)['label'] ?? 'Visão geral';
    }

    public static function getNavigationLabel(): string
    {
        return ModuleDashboards::find(static::$dashboardKey)['label'] ?? 'Visão geral';
    }

    protected function getViewData(): array
    {
        return [
            'lead' => ModuleDashboards::find(static::$dashboardKey)['lead'] ?? '',
            'blocks' => DashboardRenderer::blocks(static::$dashboardKey, auth()->user()),
        ];
    }
}
