<?php
namespace App\Filament\Resources\PartCategoryResource\Pages;
use App\Filament\Resources\PartCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPartCategory extends EditRecord
{
    protected static string $resource = PartCategoryResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
