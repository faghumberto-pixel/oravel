<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Pages;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Filament\Exports\SalesLeadExporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * So o grid, sem widgets acima -- pedido do usuario 2026-08-19 ("separe o
 * grid dos graficos"). Os 10 widgets que ficavam aqui (cards + graficos +
 * mapas) foram pra App\Filament\Central\Pages\DashboardCrm, que agora reune
 * todos os graficos do modulo comercial num so lugar.
 */
class ListSalesLeads extends ListRecords
{
    protected static string $resource = SalesLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('dashboard')
                ->label('Ver Gráficos')
                ->icon('heroicon-o-presentation-chart-line')
                ->color('gray')
                ->url(fn () => \App\Filament\Central\Pages\DashboardCrm::getUrl()),
            Actions\ExportAction::make()->exporter(SalesLeadExporter::class),
            Actions\CreateAction::make(),
        ];
    }
}
