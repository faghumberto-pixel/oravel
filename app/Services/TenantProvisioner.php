<?php

namespace App\Services;

use App\Models\EquipmentDamage;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;

class TenantProvisioner
{
    /**
     * Cria o usuário administrador do tenant recém-criado. O papel 'admin'
     * (App\Models\User::isAdmin()) já dá bypass total na trava de permissão
     * individual em AbstractPolicy::check() -- só a trava comercial (feature
     * do plano) continua valendo para ele. Por isso não é preciso conceder
     * nenhuma Permission explícita aqui: o admin do tenant automaticamente
     * tem acesso a tudo que o plano contratado libera.
     *
     * @param  array{name: string, email: string, password: string}  $adminData
     */
    public static function provision(Tenant $tenant, array $adminData): User
    {
        $role = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]
        );

        $user = User::create([
            'name' => $adminData['name'],
            'email' => $adminData['email'],
            'password' => $adminData['password'],
            'tenant_id' => $tenant->id,
            'role' => 'admin',
            'hourly_rate' => 0,
        ]);

        // 'email_verified_at' nao esta no $fillable de User (create() acima o
        // ignoraria silenciosamente) -- setado a parte de proposito.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole($role);

        foreach ([
            EquipmentDamage::ROLE_SUPERVISOR_MANUTENCAO,
            EquipmentDamage::ROLE_COMERCIAL,
            EquipmentDamage::ROLE_GERENTE_MANUTENCAO,
            EquipmentDamage::ROLE_ANALISTA_MANUTENCAO,
            'Gerente de Logística',
        ] as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
                'tenant_id' => $tenant->id,
            ]);
        }

        return $user;
    }
}
