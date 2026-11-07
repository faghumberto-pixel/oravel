<?php

namespace App\Filament\Resources\SlaHolidayResource\Pages;

use App\Filament\Resources\SlaHolidayResource;
use App\Services\Sla\SlaService;
use Filament\Resources\Pages\CreateRecord;

class CreateSlaHoliday extends CreateRecord
{
    protected static string $resource = SlaHolidayResource::class;

    protected function afterCreate(): void
    {
        SlaService::esquecer();
    }
}
