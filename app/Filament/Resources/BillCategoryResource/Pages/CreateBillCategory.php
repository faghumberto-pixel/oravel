<?php

namespace App\Filament\Resources\BillCategoryResource\Pages;

use App\Filament\Resources\BillCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBillCategory extends CreateRecord
{
    protected static string $resource = BillCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
