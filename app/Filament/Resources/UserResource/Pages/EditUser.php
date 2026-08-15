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

        $data['employee_cpf'] = $employee?->cpf;
        $data['employee_role_title'] = $employee?->role_title;
        $data['employee_admission_date'] = $employee?->admission_date?->toDateString();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->employeeData = [
            'cpf' => $data['employee_cpf'] ?? null,
            'role_title' => $data['employee_role_title'] ?? null,
            'admission_date' => $data['employee_admission_date'] ?? null,
        ];
        unset($data['employee_cpf'], $data['employee_role_title'], $data['employee_admission_date']);

        return $data;
    }

    protected function afterSave(): void
    {
        $user = $this->getRecord();

        if (blank($this->employeeData['cpf'] ?? null)) {
            return;
        }

        Employee::updateOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => $user->tenant_id,
                'department_id' => $user->department_id,
                'name' => $user->name,
                'cpf' => $this->employeeData['cpf'],
                'role_title' => $this->employeeData['role_title'],
                'admission_date' => $this->employeeData['admission_date'],
            ],
        );
    }
}
