<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Resources\MaintenanceOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceOrders extends ListRecords
{
    protected static string $resource = MaintenanceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nova Ordem de Serviço'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MaintenanceOrderResource\Widgets\MaintenanceOrderStats::class,
            MaintenanceOrderResource\Widgets\CriticalityChart::class,
            MaintenanceOrderResource\Widgets\StatusChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 2;
    }
}
