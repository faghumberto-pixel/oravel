<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\CompanyResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('company')]
class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;
}
