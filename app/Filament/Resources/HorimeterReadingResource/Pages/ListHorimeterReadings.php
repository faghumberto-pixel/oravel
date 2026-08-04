<?php

namespace App\Filament\Resources\HorimeterReadingResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\HorimeterReadingResource;
use Filament\Resources\Pages\ListRecords;

class ListHorimeterReadings extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = HorimeterReadingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
        ];
    }
}
