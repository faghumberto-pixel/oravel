<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\MaintenanceOrderExporter;
use App\Filament\Resources\MaintenanceOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceOrders extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = MaintenanceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ADIÇÃO DO BOTÃO CRIAR NO TOPO
            Actions\CreateAction::make()
                ->label('Nova Ordem de Serviço')
                ->icon('heroicon-m-plus'),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(MaintenanceOrderExporter::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MaintenanceOrderResource\Widgets\MaintenanceOrderStats::class,
            MaintenanceOrderResource\Widgets\MaintenanceOrdersEvolutionChart::class,
            MaintenanceOrderResource\Widgets\MaintenanceOrdersStatusDonutChart::class,
            MaintenanceOrderResource\Widgets\MaintenanceOrdersByTypeChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 4;
    }
}
