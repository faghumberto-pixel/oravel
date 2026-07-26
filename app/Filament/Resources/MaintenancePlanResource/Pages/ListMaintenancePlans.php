<?php

namespace App\Filament\Resources\MaintenancePlanResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\MaintenancePlanExporter;
use App\Filament\Resources\MaintenancePlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenancePlans extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = MaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(MaintenancePlanExporter::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            MaintenancePlanResource\Widgets\MaintenancePlanStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
