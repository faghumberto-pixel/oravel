<?php

namespace App\Filament\Resources\EpiDeliveryResource\Pages;

use App\Filament\Resources\EpiDeliveryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEpiDeliveries extends ListRecords
{
    protected static string $resource = EpiDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EpiDeliveryResource\Widgets\EpiComplianceStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
