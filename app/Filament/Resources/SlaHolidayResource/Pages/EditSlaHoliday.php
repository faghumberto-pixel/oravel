<?php

namespace App\Filament\Resources\SlaHolidayResource\Pages;

use App\Filament\Resources\SlaHolidayResource;
use App\Services\Sla\SlaService;
use Filament\Resources\Pages\EditRecord;

class EditSlaHoliday extends EditRecord
{
    protected static string $resource = SlaHolidayResource::class;

    protected function afterSave(): void
    {
        SlaService::esquecer();
    }
}
