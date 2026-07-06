<?php

namespace App\Filament\Resources\FreightRecordResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\FreightRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFreightRecords extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = FreightRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FreightRecordResource\Widgets\FreightRecordStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
