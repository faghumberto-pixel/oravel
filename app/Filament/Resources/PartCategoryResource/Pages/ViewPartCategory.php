<?php

namespace App\Filament\Resources\PartCategoryResource\Pages;

use App\Filament\Concerns\HasRecordPrintAction;
use App\Filament\Resources\PartCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPartCategory extends ViewRecord
{
    use HasRecordPrintAction;

    protected static string $resource = PartCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->printAction(), Actions\EditAction::make(), Actions\DeleteAction::make()];
    }
}
