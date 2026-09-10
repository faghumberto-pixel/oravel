<?php

namespace Tests\Feature;

use App\Filament\Resources\EpiDeliveryResource\Pages\CreateEpiDelivery;
use App\Filament\Resources\MaterialResource\Pages\CreateMaterial;
use App\Filament\Resources\MaterialResource\Pages\EditMaterial;
use App\Models\Employee;
use App\Models\EpiDelivery;
use App\Models\EpiSpecification;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verificacao do modulo de Gestao de EPI (branch claude/epi-management-module,
 * revisado 2026-09-10). O bloqueio real por trigger de Postgres (CA vencido)
 * so' roda em producao -- sqlite (motor de teste) nao suporta PL/pgSQL, ver
 * guard na migration create_epi_deliveries_table. Esses testes cobrem o que
 * sqlite consegue: formularios Filament e a baixa/devolucao de estoque via
 * MaterialStockService. O trigger em si foi validado manualmente contra
 * Postgres real em DEV durante a revisao (bloqueou/desbloqueou corretamente).
 */
class EpiManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano EPI '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_materials', 'tabela_epi_specifications', 'tabela_epi_deliveries', 'tabela_employees', 'tabela_internal_units'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant EPI '.uniqid(), 'slug' => 'tenant-epi-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_creating_a_regular_material_without_toggling_is_epi_does_not_create_a_specification(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CreateMaterial::class)
            ->fillForm(['name' => 'Parafuso Comum', 'sku' => 'PRF-001', 'unit_cost' => 5])
            ->call('create')
            ->assertHasNoFormErrors();

        $material = Material::where('sku', 'PRF-001')->sole();
        $this->assertNull($material->epiSpecification);
    }

    public function test_creating_a_material_with_is_epi_toggled_creates_the_specification(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CreateMaterial::class)
            ->fillForm([
                'name' => 'Luva de Proteção G',
                'sku' => 'EPI-LUVA-G',
                'unit_cost' => 12,
                'is_epi' => true,
                'epiSpecification.epi_type' => EpiSpecification::TYPE_LUVA,
                'epiSpecification.size_label' => 'G',
                'epiSpecification.ca_number' => 'CA-12345',
                'epiSpecification.ca_validade' => now()->addYear()->format('Y-m-d'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $material = Material::where('sku', 'EPI-LUVA-G')->sole();
        $this->assertNotNull($material->epiSpecification);
        $this->assertSame('CA-12345', $material->epiSpecification->ca_number);
    }

    public function test_editing_an_epi_material_prefills_the_is_epi_toggle_and_specification(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $material = Material::create(['tenant_id' => $tenant->id, 'name' => 'Capacete', 'sku' => 'EPI-CAP-01', 'unit_cost' => 40]);
        EpiSpecification::create([
            'tenant_id' => $tenant->id, 'material_id' => $material->id,
            'epi_type' => EpiSpecification::TYPE_CAPACETE, 'ca_number' => 'CA-99000',
            'ca_validade' => now()->addMonths(6),
        ]);

        Livewire::test(EditMaterial::class, ['record' => $material->getRouteKey()])
            ->assertFormSet(['is_epi' => true, 'epiSpecification.ca_number' => 'CA-99000']);
    }

    public function test_creating_an_epi_delivery_consumes_material_stock(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $employee = Employee::create(['tenant_id' => $tenant->id, 'name' => 'Técnico', 'cpf' => '12345678900', 'status' => 'ativo']);
        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Filial Teste']);
        $material = Material::create(['tenant_id' => $tenant->id, 'name' => 'Óculos de Proteção', 'sku' => 'EPI-OCULOS-01', 'current_stock' => 20, 'unit_cost' => 8]);
        EpiSpecification::create([
            'tenant_id' => $tenant->id, 'material_id' => $material->id,
            'epi_type' => EpiSpecification::TYPE_OCULOS, 'ca_number' => 'CA-55555',
            'ca_validade' => now()->addYear(),
        ]);

        Livewire::test(CreateEpiDelivery::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'material_id' => $material->id,
                'internal_unit_id' => $unit->id,
                'quantity' => 3,
                'reason' => EpiDelivery::REASON_ENTREGA_INICIAL,
                'ownership_mode' => EpiSpecification::OWNERSHIP_DEFINITIVA,
                'delivered_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $delivery = EpiDelivery::where('employee_id', $employee->id)->sole();
        $this->assertSame(17, $material->fresh()->current_stock);
        $this->assertNotNull($delivery->material_stock_movement_id);
    }
}
