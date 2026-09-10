<?php

namespace App\Filament\Resources\EpiDeliveryResource\Pages;

use App\Filament\Resources\EpiDeliveryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEpiDelivery extends CreateRecord
{
    protected static string $resource = EpiDeliveryResource::class;

    protected function afterCreate(): void
    {
        EpiDeliveryResource::consumeStockForDelivery($this->record);
    }
}
