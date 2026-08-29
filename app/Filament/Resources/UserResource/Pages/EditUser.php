<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Employee;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Campos do bloco "Vínculo com Departamento Pessoal" não existem em
     * `users` -- guardamos aqui antes de sumirem do $data e usamos em
     * afterSave() pra criar/atualizar o Employee correspondente.
     */
    protected array $employeeData = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $employee = Employee::where('user_id', $data['id'])->first();

        $data['is_employee'] = $employee !== null;
        $data['employee_cpf'] = $employee?->cpf;
        $data['employee_role_title'] = $employee?->role_title;
        $data['employee_admission_date'] = $employee?->admission_date?->toDateString();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->employeeData = [
            'is_employee' => (bool) ($data['is_employee'] ?? false),
            'cpf' => $data['employee_cpf'] ?? null,
            'role_title' => $data['employee_role_title'] ?? null,
            'admission_date' => $data['employee_admission_date'] ?? null,
        ];
        unset($data['is_employee'], $data['employee_cpf'], $data['employee_role_title'], $data['employee_admission_date']);

        return $data;
    }

    /**
     * Desligar o toggle NUNCA apaga o Employee (perderia CPF real/histórico
     * já preenchido) -- só desvincula (user_id = null), mesmo padrão que o
     * EmployeeResource já suporta pra colaborador avulso/terceirizado.
     */
    protected function afterSave(): void
    {
        $user = $this->getRecord();
        $employee = Employee::where('user_id', $user->id)->first();

        if (! ($this->employeeData['is_employee'] ?? false)) {
            $employee?->update(['user_id' => null]);

            return;
        }

        $cpf = $this->employeeData['cpf'] ?? null;

        Employee::updateOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => $user->tenant_id,
                'department_id' => $user->department_id,
                'name' => $user->name,
                'cpf' => blank($cpf) ? ($employee?->cpf ?? Employee::nextPlaceholderCpf($user->tenant_id)) : $cpf,
                'role_title' => $this->employeeData['role_title'],
                'admission_date' => $this->employeeData['admission_date'],
                'status' => blank($cpf) ? ($employee?->status ?? Employee::STATUS_INCOMPLETO) : Employee::STATUS_ATIVO,
            ],
        );
    }
}
