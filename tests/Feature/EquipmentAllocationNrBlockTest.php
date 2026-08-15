<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Models\EmployeeCertification;
use App\Models\EquipmentAllocation;
use App\Models\NrRequirementByCategory;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre a trava real de negocio do modulo Departamento Pessoal: um
 * colaborador nao pode ficar alocado num Asset sem certificacao NR
 * vigente para a categoria daquele ativo -- reforcada por trigger de
 * banco (nao so' validacao de app), ver
 * database/migrations/..._create_equipment_allocations_table.php.
 */
class EquipmentAllocationNrBlockTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano alocação '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);

        return Tenant::create([
            'name' => 'Tenant alocação '.uniqid(), 'slug' => 'tenant-alocacao-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);
    }

    public function test_allocation_is_blocked_when_employee_has_no_valid_certification(): void
    {
        $tenant = $this->makeTenant();

        $category = AssetCategory::create([
            'tenant_id' => $tenant->id, 'name' => 'Plataforma Elevatória',
        ]);
        NrRequirementByCategory::create([
            'tenant_id' => $tenant->id, 'asset_category_id' => $category->id, 'norma' => 'NR-35',
        ]);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Plataforma 12m', 'tag' => 'PLAT-001',
            'asset_category_id' => $category->id, 'status' => 'disponivel',
        ]);
        $employee = Employee::create([
            'tenant_id' => $tenant->id, 'name' => 'Operador Sem Certificação', 'cpf' => '11122233344',
        ]);

        $allocation = EquipmentAllocation::create([
            'tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'asset_id' => $asset->id,
        ]);

        $this->assertTrue($allocation->fresh()->blocked);
        $this->assertStringContainsString('NR-35', $allocation->fresh()->blocked_reason);
    }

    public function test_allocation_is_not_blocked_when_employee_has_valid_certification(): void
    {
        $tenant = $this->makeTenant();

        $category = AssetCategory::create([
            'tenant_id' => $tenant->id, 'name' => 'Plataforma Elevatória',
        ]);
        NrRequirementByCategory::create([
            'tenant_id' => $tenant->id, 'asset_category_id' => $category->id, 'norma' => 'NR-35',
        ]);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Plataforma 12m', 'tag' => 'PLAT-002',
            'asset_category_id' => $category->id, 'status' => 'disponivel',
        ]);
        $employee = Employee::create([
            'tenant_id' => $tenant->id, 'name' => 'Operador Certificado', 'cpf' => '22233344455',
        ]);
        EmployeeCertification::create([
            'tenant_id' => $tenant->id, 'employee_id' => $employee->id,
            'norma' => 'NR-35', 'data_validade' => now()->addYear(),
        ]);

        $allocation = EquipmentAllocation::create([
            'tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'asset_id' => $asset->id,
        ]);

        $this->assertFalse($allocation->fresh()->blocked);
    }

    public function test_allocation_is_blocked_when_certification_is_expired(): void
    {
        $tenant = $this->makeTenant();

        $category = AssetCategory::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira',
        ]);
        NrRequirementByCategory::create([
            'tenant_id' => $tenant->id, 'asset_category_id' => $category->id, 'norma' => 'NR-11',
        ]);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira 2.5t', 'tag' => 'EMP-001',
            'asset_category_id' => $category->id, 'status' => 'disponivel',
        ]);
        $employee = Employee::create([
            'tenant_id' => $tenant->id, 'name' => 'Operador Vencido', 'cpf' => '33344455566',
        ]);
        EmployeeCertification::create([
            'tenant_id' => $tenant->id, 'employee_id' => $employee->id,
            'norma' => 'NR-11', 'data_validade' => now()->subDay(),
        ]);

        $allocation = EquipmentAllocation::create([
            'tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'asset_id' => $asset->id,
        ]);

        $this->assertTrue($allocation->fresh()->blocked);
    }

    public function test_allocation_is_not_blocked_when_category_has_no_nr_requirement(): void
    {
        $tenant = $this->makeTenant();

        $category = AssetCategory::create([
            'tenant_id' => $tenant->id, 'name' => 'Ferramenta Manual',
        ]);
        // Sem NrRequirementByCategory pra esta categoria -- nenhuma norma exigida.
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Furadeira', 'tag' => 'FER-001',
            'asset_category_id' => $category->id, 'status' => 'disponivel',
        ]);
        $employee = Employee::create([
            'tenant_id' => $tenant->id, 'name' => 'Qualquer Colaborador', 'cpf' => '44455566677',
        ]);

        $allocation = EquipmentAllocation::create([
            'tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'asset_id' => $asset->id,
        ]);

        $this->assertFalse($allocation->fresh()->blocked);
    }
}
