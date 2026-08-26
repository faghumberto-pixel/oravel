<?php

namespace Database\Seeders;

use App\Models\ClientMessage;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class ClientMessageAreaRolesSeeder extends Seeder
{
    public function run(): void
    {
        $roleNames = array_map(
            fn (string $area) => ClientMessage::areaRoleName($area),
            array_keys(ClientMessage::areaLabels())
        );

        foreach (Tenant::all() as $tenant) {
            foreach ($roleNames as $roleName) {
                Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'tenant_id' => $tenant->id,
                ]);
            }
        }
    }
}
