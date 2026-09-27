<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\FleetDriver;
use App\Models\JobRole;
use Illuminate\Database\Seeder;

/**
 * One-off, pedido pelo usuario 27/09/2026: criar um Tecnico e um Motorista
 * de exemplo pro tenant real "Locacao Silva", pra ter dado com que testar
 * Funcoes e Cargos + Motoristas. CPF placeholder (mesmo padrao de
 * tenant:backfill-employees) ate RH completar com o CPF real.
 */
class LocacaoSilvaTecnicoMotoristaSeeder extends Seeder
{
    private const TENANT_ID = '01a0d4e9-0cda-73d8-8d24-53ed0e2a8b7b';

    public function run(): void
    {
        $tecnicoRole = JobRole::withoutGlobalScopes()
            ->where('tenant_id', self::TENANT_ID)
            ->where('name', 'Técnico')
            ->first();

        $motoristaRole = JobRole::withoutGlobalScopes()
            ->firstOrCreate(
                ['tenant_id' => self::TENANT_ID, 'name' => 'Motorista'],
            );

        $tecnico = Employee::withoutGlobalScopes()->create([
            'tenant_id' => self::TENANT_ID,
            'name' => 'Técnico Exemplo',
            'cpf' => Employee::nextPlaceholderCpf(self::TENANT_ID),
            'role_title' => 'Técnico de Campo',
            'job_role_id' => $tecnicoRole?->id,
            'status' => Employee::STATUS_INCOMPLETO,
        ]);

        $motorista = Employee::withoutGlobalScopes()->create([
            'tenant_id' => self::TENANT_ID,
            'name' => 'Motorista Exemplo',
            'cpf' => Employee::nextPlaceholderCpf(self::TENANT_ID),
            'role_title' => 'Motorista',
            'job_role_id' => $motoristaRole->id,
            'status' => Employee::STATUS_INCOMPLETO,
        ]);

        FleetDriver::withoutGlobalScopes()->create([
            'tenant_id' => self::TENANT_ID,
            'employee_id' => $motorista->id,
            'name' => $motorista->name,
            'employment_type' => FleetDriver::EMPLOYMENT_PROPRIO,
            'active' => true,
        ]);

        $this->command?->info('Técnico e Motorista de exemplo criados pra Locação Silva.');
    }
}
