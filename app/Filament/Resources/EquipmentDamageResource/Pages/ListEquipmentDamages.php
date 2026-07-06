<?php

namespace App\Filament\Resources\EquipmentDamageResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\EquipmentDamageResource;
use Filament\Resources\Pages\ListRecords;

class ListEquipmentDamages extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = EquipmentDamageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EquipmentDamageResource\Widgets\EquipmentDamageStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
