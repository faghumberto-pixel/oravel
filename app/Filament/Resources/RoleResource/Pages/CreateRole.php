<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function afterCreate(): void
    {
        $permissions = collect($this->form->getRawState())
            ->filter(fn ($v, $k) => str_starts_with($k, 'perm_') && $v === true)
            ->map(fn ($v, $k) => str_replace('perm_', '', $k))
            ->toArray();
        $this->record->syncPermissions($permissions);
    }
}