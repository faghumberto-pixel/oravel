<?php

namespace App\Filament\Resources\BillCategoryResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\BillCategoryResource;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('bill_categories')]
class CreateBillCategory extends CreateRecord
{
    protected static string $resource = BillCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}