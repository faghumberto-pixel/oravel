<?php

namespace Database\Seeders;

use App\Models\EquipmentDamage;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class EquipmentDamageRolesSeeder extends Seeder
{
    public function run(): void
    {
        $roleNames = [
            EquipmentDamage::ROLE_SUPERVISOR_MANUTENCAO,
            EquipmentDamage::ROLE_COMERCIAL,
            EquipmentDamage::ROLE_GERENTE_MANUTENCAO,
        ];

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
