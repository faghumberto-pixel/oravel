<?php

namespace App\Filament\Resources\EpiDeliveryResource\Pages;

use App\Filament\Resources\EpiDeliveryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEpiDelivery extends EditRecord
{
    protected static string $resource = EpiDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
