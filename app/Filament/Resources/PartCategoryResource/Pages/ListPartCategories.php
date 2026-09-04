<?php
namespace App\Filament\Resources\PartCategoryResource\Pages;
use App\Filament\Resources\PartCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPartCategories extends ListRecords
{
    protected static string $resource = PartCategoryResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
