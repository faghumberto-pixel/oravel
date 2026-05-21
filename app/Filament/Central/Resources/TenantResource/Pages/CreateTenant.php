<?php

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * Ciclo de vida do Filament: Executado imediatamente APÓS o Tenant ser salvo no banco.
     */
    protected function afterCreate(): void
    {
        // 1. Pega o objeto da Empresa que acabou de ser criada (contém o ID novo)
        $tenant = $this->record;

        // 2. Recupera as informações digitadas nos campos do Administrador
        // vindas do formulário ($this->data)
        $adminName = $this->data['admin_name'] ?? null;
        $adminEmail = $this->data['admin_email'] ?? null;
        $adminPassword = $this->data['admin_password'] ?? null;

        // Proteção contra dados vazios
        if (! $adminEmail || ! $adminPassword) {
            return;
        }

        // 3. Cria o usuário vinculando o 'tenant_id' direto (Relação 1:N se aplicável)
        $user = User::create([
            'name' => $adminName,
            'email' => $adminEmail,
            'password' => Hash::make($adminPassword),
            'tenant_id' => $tenant->id, 
            'hourly_rate' => 0.00, // Preenche campos obrigatórios se houver
        ]);

        // 4. Cria o relacionamento Pivot (N:N na tabela tenant_user)
        // Isso é o que impede o temido Erro 404 que o Renan enfrentou!
        $user->tenants()->attach($tenant->id);

        // 5. Garante que a role de 'admin' existe e atribui ao usuário
        // Dando a ele o poder total que a sua SaaSResourcePolicy exige
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user->assignRole('admin');
    }
}