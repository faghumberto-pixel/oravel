<?php

namespace Tests\Feature;

use App\Livewire\PreventiveMaintenanceMobile;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\PreventiveMaintenanceExecution;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Versao pro campo (celular do tecnico) do Plano de Preventiva -- checkbox
 * simples por item, sem formulario complexo, com opcao de impressao.
 */
class PreventiveMaintenanceMobileTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Preventiva Mobile '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders', 'tabela_maintenance_plans', 'tabela_preventive_maintenance_executions'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Preventiva Mobile '.uniqid(), 'slug' => 'tenant-preventiva-mobile-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Tecnico', 'email' => 'tecnico-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeOrderWithPlans(Tenant $tenant, User $technician, float $horimetroAtual = 300): array
    {
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Geradores de Energia']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Mobile', 'tag' => 'AST-'.uniqid(),
            'patrimonio' => 'PAT-'.uniqid(), 'status' => 'disponivel',
            'checklist_group_id' => $group->id, 'horimetro_atual' => $horimetroAtual,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo do motor', 'interval_hours' => 250, 'is_active' => true,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de filtro de ar', 'interval_hours' => 500, 'is_active' => true,
        ]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $technician->id,
            'description' => 'Preventiva de campo', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        return [$asset, $order];
    }

    public function test_mobile_page_lists_items_with_due_status(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$asset, $order] = $this->makeOrderWithPlans($tenant, $admin, horimetroAtual: 300);

        $this->actingAs($admin)
            ->get(route('maintenance-orders.preventiva-mobile', $order))
            ->assertOk()
            ->assertSee('Troca de óleo do motor')
            ->assertSee('Troca de filtro de ar')
            ->assertSee('VENCIDO', false);
    }

    public function test_checking_an_item_creates_execution_and_advances_progress(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$asset, $order] = $this->makeOrderWithPlans($tenant, $admin);
        $plan = MaintenancePlan::where('tenant_id', $tenant->id)->where('name', 'Troca de óleo do motor')->first();

        Livewire::actingAs($admin)
            ->test(PreventiveMaintenanceMobile::class, ['maintenanceOrder' => $order])
            ->set('horimetro', 320)
            ->assertSet('progress', 0)
            ->call('toggleItem', $plan->id)
            ->assertSet('progress', 50);

        $this->assertSame(1, PreventiveMaintenanceExecution::where('maintenance_plan_id', $plan->id)->count());
        $this->assertEquals(320, $asset->fresh()->horimetro_atual);
    }

    public function test_unchecking_an_item_deletes_the_execution(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$asset, $order] = $this->makeOrderWithPlans($tenant, $admin);
        $plan = MaintenancePlan::where('tenant_id', $tenant->id)->where('name', 'Troca de óleo do motor')->first();

        Livewire::actingAs($admin)
            ->test(PreventiveMaintenanceMobile::class, ['maintenanceOrder' => $order])
            ->set('horimetro', 320)
            ->call('toggleItem', $plan->id)
            ->call('toggleItem', $plan->id)
            ->assertSet('progress', 0);

        $this->assertSame(0, PreventiveMaintenanceExecution::where('maintenance_plan_id', $plan->id)->count());
    }

    public function test_checking_an_item_without_horimetro_shows_validation_error(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$asset, $order] = $this->makeOrderWithPlans($tenant, $admin);
        $plan = MaintenancePlan::where('tenant_id', $tenant->id)->where('name', 'Troca de óleo do motor')->first();

        Livewire::actingAs($admin)
            ->test(PreventiveMaintenanceMobile::class, ['maintenanceOrder' => $order])
            ->set('horimetro', null)
            ->call('toggleItem', $plan->id)
            ->assertHasErrors(['horimetro']);

        $this->assertSame(0, PreventiveMaintenanceExecution::where('maintenance_plan_id', $plan->id)->count());
    }

    public function test_user_of_another_tenant_cannot_view_the_order(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        [$assetA, $order] = $this->makeOrderWithPlans($tenantA, $adminA);

        [$tenantB, $adminB] = $this->makeTenantAdmin();

        $this->actingAs($adminB)
            ->get(route('maintenance-orders.preventiva-mobile', $order))
            ->assertNotFound();
    }

    public function test_print_route_renders_the_checklist(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$asset, $order] = $this->makeOrderWithPlans($tenant, $admin);

        $this->actingAs($admin)
            ->get(route('maintenance-orders.preventiva.print', $order))
            ->assertOk()
            ->assertSee('Troca de óleo do motor')
            ->assertSee($order->os_number);
    }

    public function test_print_route_does_not_leak_another_tenants_order(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        [$assetA, $order] = $this->makeOrderWithPlans($tenantA, $adminA);

        [$tenantB, $adminB] = $this->makeTenantAdmin();

        $this->actingAs($adminB)
            ->get(route('maintenance-orders.preventiva.print', $order))
            ->assertNotFound();
    }
}
