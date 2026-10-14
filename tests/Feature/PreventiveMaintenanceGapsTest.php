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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regressao dos 3 buracos achados na conferencia da aula de Manutencao
 * Preventiva (14/10/2026): plano so de ativo nao aparecia no celular, a
 * "proxima previsao" ignorava planos por dias/ciclos e plano desativado
 * continuava alertando na verificacao diaria.
 */
class PreventiveMaintenanceGapsTest extends TestCase
{
    use DatabaseTransactions;

    private function setUpTenant(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Gaps '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders', 'tabela_maintenance_plans', 'tabela_preventive_maintenance_executions'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Gaps '.uniqid(), 'slug' => 'tenant-gaps-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $user = User::create([
            'name' => 'Tecnico', 'email' => 'gaps-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $role = (new Role(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $role->save();
        $user->assignRole($role);

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Gaps', 'tag' => 'AST-'.uniqid(),
            'patrimonio' => 'PAT-'.uniqid(), 'status' => 'disponivel', 'horimetro_atual' => 300,
        ]);

        return [$tenant, $user, $asset];
    }

    private function order(Tenant $tenant, User $user, Asset $asset): MaintenanceOrder
    {
        return MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $user->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);
    }

    public function test_plan_only_for_the_asset_shows_up_and_can_be_checked_on_mobile(): void
    {
        [$tenant, $user, $asset] = $this->setUpTenant();
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Troca de correia', 'interval_hours' => 100, 'is_active' => true,
        ]);
        $order = $this->order($tenant, $user, $asset);

        $this->actingAs($user)
            ->get(route('maintenance-orders.preventiva-mobile', $order))
            ->assertOk()
            ->assertSee('Troca de correia');

        Livewire::actingAs($user)
            ->test(PreventiveMaintenanceMobile::class, ['maintenanceOrder' => $order])
            ->set('horimetro', 320)
            ->call('toggleItem', $plan->id)
            ->assertSet('progress', 100);

        // A execucao zera o vencimento tambem no plano individual.
        $this->assertFalse($plan->fresh()->dueStatusForAsset($asset->fresh())['is_overdue']);
    }

    public function test_asset_plan_with_same_name_overrides_the_group_plan_on_mobile(): void
    {
        [$tenant, $user, $asset] = $this->setUpTenant();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset->update(['checklist_group_id' => $group->id]);
        MaintenancePlan::create(['tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de oleo', 'interval_hours' => 250, 'is_active' => true]);
        MaintenancePlan::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de oleo', 'interval_hours' => 500, 'is_active' => true]);

        $items = Livewire::actingAs($user)
            ->test(PreventiveMaintenanceMobile::class, ['maintenanceOrder' => $this->order($tenant, $user, $asset)])
            ->instance()->items;

        $this->assertCount(1, $items);
        $this->assertSame(500, (int) $items[0]['plan']->interval_hours);
    }

    public function test_execution_of_a_days_only_plan_has_no_next_due_horimetro(): void
    {
        [$tenant, $user, $asset] = $this->setUpTenant();
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Inspecao mensal', 'interval_days' => 30, 'is_active' => true,
        ]);

        $execution = PreventiveMaintenanceExecution::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plan->id,
            'maintenance_order_id' => $this->order($tenant, $user, $asset)->id,
            'technician_id' => $user->id, 'horimetro_at_execution' => 310,
        ]);

        $this->assertNull($execution->fresh()->next_due_horimetro);
    }

    public function test_execution_of_an_hours_plan_still_projects_next_due(): void
    {
        [$tenant, $user, $asset] = $this->setUpTenant();
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Oleo', 'interval_hours' => 250, 'is_active' => true,
        ]);

        $execution = PreventiveMaintenanceExecution::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plan->id,
            'maintenance_order_id' => $this->order($tenant, $user, $asset)->id,
            'technician_id' => $user->id, 'horimetro_at_execution' => 310,
        ]);

        $this->assertEquals(560, $execution->fresh()->next_due_horimetro);
    }

    public function test_daily_check_ignores_inactive_plans(): void
    {
        [$tenant, $user, $asset] = $this->setUpTenant();
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Desligado',
            'interval_hours' => 100, 'is_active' => false, 'is_critical' => true, 'auto_create_order' => true,
        ]);

        Artisan::call('maintenance:check-due-alerts');

        $this->assertSame(0, MaintenanceOrder::where('asset_id', $asset->id)->where('origin', 'pmp_auto')->count());
        $this->assertSame('disponivel', $asset->fresh()->status);
    }

    public function test_daily_check_still_acts_on_active_plans(): void
    {
        [$tenant, $user, $asset] = $this->setUpTenant();
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Ligado',
            'interval_hours' => 100, 'is_active' => true, 'auto_create_order' => true,
        ]);

        Artisan::call('maintenance:check-due-alerts');

        $this->assertSame(1, MaintenanceOrder::where('asset_id', $asset->id)->where('origin', 'pmp_auto')->count());
    }
}
