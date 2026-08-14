<?php

namespace App\Services;

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

        // 8 setores (Comercial, Manutenção, Ativos e Materiais, Logística,
        // Financeiro, Administrativo, Departamento Pessoal, Segurança do
        // Trabalho) + cargos de cada um, prontos pro tenant usar ou
        // ajustar -- ver App\Services\OrganizationalStructureSeeder.
        OrganizationalStructureSeeder::seed($tenant);

        return $user;
    }
}
