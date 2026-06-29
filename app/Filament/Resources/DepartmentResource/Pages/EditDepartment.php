<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('departments')]
class EditDepartment extends EditRecord
{
    protected static string $resource = DepartmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
