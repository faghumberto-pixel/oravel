<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\ContractExporter;
use App\Filament\Resources\ContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

#[BelongsToFeature('contracts')]
class ListContracts extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(ContractExporter::class),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ContractResource\Widgets\ContractStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
