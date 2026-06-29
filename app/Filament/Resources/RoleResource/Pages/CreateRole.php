<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

#[BelongsToFeature('roles')]
class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        // Carimba o tenant do usuario logado (admin da empresa).
        if ($user && ! $user->isSuperAdmin() && filled($user->tenant_id)) {
            $data['tenant_id'] = $user->tenant_id;
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
