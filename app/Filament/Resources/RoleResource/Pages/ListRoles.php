<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\RoleExporter;
use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

#[BelongsToFeature('roles')]
class ListRoles extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
            Actions\ExportAction::make()->exporter(RoleExporter::class),
        ];
    }
}
