<?php

namespace App\Filament\Central\Resources\RoleResource\Pages;

use App\Filament\Central\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Cria o role apenas com os campos diretos (name, guard_name).
        $role = Role::create([
            'name'       => $data['name'],
            'guard_name' => 'web',
        ]);

        $this->syncPermissionsFromToggles($role, $data);

        return $role;
    }

    protected function syncPermissionsFromToggles(Role $role, array $data): void
    {
        // getRawState() inclui campos dehydrated(false) — $data nao inclui.
        $state = $this->form->getRawState();
        $names = [];
        foreach (RoleResource::existingPermissionNames() as $permName) {
            if (! empty($state["perm_{$permName}"])) {
                $names[] = $permName;
            }
        }
        $role->syncPermissions($names);
    }
}
