<?php
namespace App\Filament\Resources\PartCategoryResource\Pages;
use App\Filament\Resources\PartCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPartCategory extends ViewRecord
{
    protected static string $resource = PartCategoryResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make(), Actions\DeleteAction::make()];
    }
}
