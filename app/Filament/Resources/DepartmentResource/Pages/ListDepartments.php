<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\DepartmentExporter;
use App\Filament\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

#[BelongsToFeature('departments')]
class ListDepartments extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(DepartmentExporter::class),
        ];
    }
}
