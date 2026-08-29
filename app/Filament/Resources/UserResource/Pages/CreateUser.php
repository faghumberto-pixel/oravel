<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Employee;
use App\Support\Tenancy;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Campos do bloco "Vínculo com Departamento Pessoal" não existem em
     * `users` -- guardamos aqui antes de sumirem do $data e usamos em
     * afterCreate() pra criar o Employee correspondente.
     */
    protected array $employeeData = [];

    /**
     * 🔄 INTERCEPTADOR DE SALVAMENTO MULTI-TENANT
     * Modifica os dados antes de salvar no banco, garantindo que o novo funcionário
     * nasça com o tenant_id preenchido e seja vinculado à empresa locadora atual.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->employeeData = [
            'is_employee' => (bool) ($data['is_employee'] ?? false),
            'cpf' => $data['employee_cpf'] ?? null,
            'role_title' => $data['employee_role_title'] ?? null,
            'admission_date' => $data['employee_admission_date'] ?? null,
        ];
        unset($data['is_employee'], $data['employee_cpf'], $data['employee_role_title'], $data['employee_admission_date']);

        $tenant = Tenancy::current();

        if ($tenant) {
            // Se a sua tabela users tiver a coluna direta tenant_id
            $data['tenant_id'] = $tenant->id;
        }

        return $data;
    }

    /**
     * 🔗 VÍNCULO DA TABELA PIVOT POST-CREATE
     * Caso o seu sistema use o relacionamento Many-to-Many (pivot tenant_user),
     * este hook garante que o vínculo seja escrito logo após a criação do ID.
     */
    protected function afterCreate(): void
    {
        $tenant = Tenancy::current();
        $user = $this->getRecord();

        if ($tenant && $user) {
            // Se houver o relacionamento 'tenants' no Model User, faz a sincronização física
            if (method_exists($user, 'tenants')) {
                $user->tenants()->syncWithoutDetaching([$tenant->id]);
            }
        }

        $this->syncEmployee($user, $tenant?->id);
    }

    /**
     * "Tornar Colaborador" (toggle) é quem decide se o vínculo com
     * Departamento Pessoal existe -- sem CPF preenchido, nasce com CPF
     * placeholder e status Incompleto (mesmo padrão de
     * tenant:backfill-employees), em vez de bloquear a criação.
     */
    protected function syncEmployee($user, ?string $tenantId): void
    {
        if (! $tenantId || ! ($this->employeeData['is_employee'] ?? false)) {
            return;
        }

        $cpf = $this->employeeData['cpf'] ?? null;

        Employee::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'department_id' => $user->department_id,
            'name' => $user->name,
            'cpf' => blank($cpf) ? Employee::nextPlaceholderCpf($tenantId) : $cpf,
            'role_title' => $this->employeeData['role_title'],
            'admission_date' => $this->employeeData['admission_date'],
            'status' => blank($cpf) ? Employee::STATUS_INCOMPLETO : Employee::STATUS_ATIVO,
        ]);
    }

    /**
     * 🔄 REDIRECIONAMENTO SEGURO
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
