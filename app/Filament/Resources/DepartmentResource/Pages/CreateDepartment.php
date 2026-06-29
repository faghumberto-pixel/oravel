<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('departments')]
class CreateDepartment extends CreateRecord
{
    protected static string $resource = DepartmentResource::class;
}
