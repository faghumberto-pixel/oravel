<?php

namespace App\Filament\Central\Resources\TenantResource\Pages;

use App\Filament\Central\Resources\TenantResource;
use App\Models\User;
use App\Models\Plan;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /** Campos que pertencem ao ADMIN, nao a tabela tenants. */
    protected array $adminData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // E-mail @oravel.com.br viraria SUPER admin sem querer -> bloqueia.
        if (str_ends_with(strtolower($data['admin_email'] ?? ''), '@oravel.com.br')) {
            throw ValidationException::withMessages([
                'data.admin_email' => 'E-mails @oravel.com.br sao reservados para super admins da plataforma.',
            ]);
        }

        // Colunas NOT NULL sem default: garante valor mesmo se o form nao preencher.
        if (($data['mrr_value'] ?? null) === null) {
            $data['mrr_value'] = optional(Plan::find($data['plan_id'] ?? null))->final_price ?? 0;
        }
        $data['status'] = $data['status'] ?? 'trial';

        $this->adminData = Arr::only($data, ['admin_name', 'admin_email', 'admin_password']);

        return Arr::except($data, ['admin_name', 'admin_email', 'admin_password']);
    }

    protected function afterCreate(): void
    {
        $tenant = $this->record;
        $admin  = $this->adminData;

        // Nao roubar um usuario que ja pertence a outra empresa.
        $existing = User::where('email', $admin['admin_email'])->first();
        if ($existing && $existing->tenant_id && (string) $existing->tenant_id !== (string) $tenant->id) {
            throw new \RuntimeException(
                "O e-mail {$admin['admin_email']} ja esta vinculado a outro tenant."
            );
        }

        // 1. Cria (ou recupera) o admin ja carimbado com o tenant.
        $user = User::firstOrCreate(
            ['email' => $admin['admin_email']],
            [
                'name'      => $admin['admin_name'] ?: 'Administrador',
                'password'  => Hash::make($admin['admin_password']),
                'tenant_id' => $tenant->id,
                'role'      => 'admin',
            ]
        );

        // Se ja existia (mesmo tenant), garante vinculo sem trocar a senha.
        if (! $user->wasRecentlyCreated) {
            $user->forceFill(['tenant_id' => $tenant->id, 'role' => 'admin'])->save();
        }

        // 2. Pivot tenant_user (pelo lado do User).
        $user->tenants()->syncWithoutDetaching([$tenant->id]);

        // 3. Role 'admin' GLOBAL e compartilhada (isAdmin() exige name='admin').
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // teams DESLIGADO -> assignRole recebe APENAS a role.
        $user->assignRole($role);
    }
}
