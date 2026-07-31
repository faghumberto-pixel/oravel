<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;

class GrantTechnicianPermissions extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'teste-tecnico')->first();
        if (!$tenant) {
            echo "✗ Tenant não encontrado\n";
            return;
        }

        $user = User::where('email', 'tecnico@teste.local')->first();
        if (!$user) {
            echo "✗ Usuário não encontrado\n";
            return;
        }

        // Criar role de técnico
        $techRole = Role::firstOrCreate(
            ['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id],
            ['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );

        // Atribuir role ao usuário
        $user->assignRole($techRole);

        // Permissões para Ordens de Serviço
        $permissions = [
            'ler_maintenance_order',
            'criar_maintenance_order',
            'editar_maintenance_order',
            'excluir_maintenance_order',
        ];

        foreach ($permissions as $perm) {
            $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            $techRole->givePermissionTo($p);
        }

        echo "✓ Role técnico criada e permissões atribuídas\n";
    }
}
