<?php

namespace App\Filament\Resources\BillCategoryResource\Pages;

use App\Filament\Resources\BillCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBillCategory extends EditRecord
{
    protected static string $resource = BillCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
