<?php

namespace App\Filament\Resources\FreightCarrierResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\FreightCarrierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFreightCarriers extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = FreightCarrierResource::class;

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
            FreightCarrierResource\Widgets\FreightCarrierStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
