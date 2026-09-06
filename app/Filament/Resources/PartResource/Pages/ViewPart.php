<?php

namespace App\Filament\Resources\PartResource\Pages;

use App\Filament\Concerns\HasRecordPrintAction;
use App\Filament\Resources\PartResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPart extends ViewRecord
{
    use HasRecordPrintAction;

    protected static string $resource = PartResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->printAction(), Actions\EditAction::make(), Actions\DeleteAction::make()];
    }
}
