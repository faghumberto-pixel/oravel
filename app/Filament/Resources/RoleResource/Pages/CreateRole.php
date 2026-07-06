<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\RoleResource;
use App\Support\Tenancy;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('roles')]
class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Mesma fonte que RoleResource::getEloquentQuery() -- carimba o
        // tenant atuante (do usuario logado, ou o escolhido pelo super
        // admin), nao so o tenant_id direto do usuario.
        if ($tenantId = Tenancy::current()?->id) {
            $data['tenant_id'] = $tenantId;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $permissions = collect($this->form->getRawState())
            ->filter(fn ($v, $k) => str_starts_with($k, 'perm_') && $v === true)
            ->map(fn ($v, $k) => str_replace('perm_', '', $k))
            ->toArray();
        $this->record->syncPermissions($permissions);
    }
}
