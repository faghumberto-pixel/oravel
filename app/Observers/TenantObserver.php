<?php

namespace App\Observers;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class TenantObserver
{
    /**
     * Handle the Tenant "created" event.
     */
    public function created(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            // 1. Garantir Plano Padrão e Status Ativo
            if (! $tenant->plan_id) {
                $defaultPlan = Plan::where('name', 'BASIC')->first();
                if ($defaultPlan) {
                    $tenant->update([
                        'plan_id' => $defaultPlan->id,
                        'status' => 'active',
                    ]);
                }
            }

            // 2. Provisionamento de Estrutura (Departamentos)
            $tenant->departments()->createMany([
                ['name' => 'Administrativo'],
                ['name' => 'Operacional'],
                ['name' => 'Comercial'],
            ]);

            // 3. Criação de Role de forma segura (Resolve o erro UniqueConstraintViolationException)
            // Buscamos a role existente no banco para não violar a constraint global
            $role = Role::where('name', 'Administrador')
                ->where('guard_name', 'web')
                ->first();

            // Se não existir, criamos
            if (! $role) {
                $role = Role::create([
                    'name' => 'Administrador',
                    'guard_name' => 'web',
                    'tenant_id' => $tenant->id,
                ]);
            }

            // 4. Criação do Administrador Automático
            $password = Str::random(10);
            $admin = User::create([
                'name' => 'Administrador '.$tenant->name,
                'email' => 'admin@'.$tenant->slug.'.com',
                'password' => Hash::make($password),
                'temp_password' => $password,
                'tenant_id' => $tenant->id,
            ]);

            // Vincular ao Tenant e atribuir Role
            $tenant->users()->attach($admin->id);
            $admin->assignRole($role);

            // Log de backup para segurança
            logger("Provisionamento SaaS [{$tenant->name}]: Admin: {$admin->email} | Senha: {$password}");
        });
    }

    public function updated(Tenant $tenant): void {}

    public function deleted(Tenant $tenant): void {}

    public function restored(Tenant $tenant): void {}

    public function forceDeleted(Tenant $tenant): void {}
}
