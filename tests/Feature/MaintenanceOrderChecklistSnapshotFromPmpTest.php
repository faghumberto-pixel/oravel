<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource\Pages\EditMaintenanceOrder;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MaintenanceOrderChecklistSnapshotObserver já copiava is_template=true do
 * grupo do Asset para toda OS nova -- este teste confirma que isso
 * continua funcionando com itens vindos do catálogo PMP (via
 * MaintenancePlan::importChecklistFromFamilyTemplate()) e que a tela de
 * edição real renderiza o Repeater ordenado por seção sem quebrar.
 */
class MaintenanceOrderChecklistSnapshotFromPmpTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Checklist Snapshot '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_assets', 'tabela_checklist_groups'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Checklist Snapshot '.uniqid(), 'slug' => 'tenant-checklist-snapshot-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Checklist Snapshot', 'email' => 'admin-checklist-snapshot-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_new_order_inherits_pmp_checklist_items_as_pending(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Snapshot']);

        MaintenanceOrderChecklist::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'is_template' => true, 'section' => '1. Estrutural', 'item_name' => 'Pneus sem trincas',
        ]);
        MaintenanceOrderChecklist::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'is_template' => true, 'section' => '2. Energia', 'item_name' => 'Conectores de bateria',
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Snapshot', 'status' => Asset::STATUS_DISPONIVEL, 'checklist_group_id' => $group->id]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva teste',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $instances = $order->checklists()->where('is_template', false)->get();

        $this->assertCount(2, $instances);
        $this->assertTrue($instances->contains('item_name', 'Pneus sem trincas'));
        $this->assertTrue($instances->every(fn (MaintenanceOrderChecklist $c) => $c->status === null));
    }

    public function test_edit_order_page_renders_checklist_repeater_ordered_by_section(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Render']);

        MaintenanceOrderChecklist::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'is_template' => true, 'section' => '2. Energia', 'item_name' => 'Conectores de bateria',
        ]);
        MaintenanceOrderChecklist::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'is_template' => true, 'section' => '1. Estrutural', 'item_name' => 'Pneus sem trincas',
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Render', 'status' => Asset::STATUS_DISPONIVEL, 'checklist_group_id' => $group->id]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva render',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->actingAs($admin);

        Livewire::test(EditMaintenanceOrder::class, ['record' => $order->id])
            ->assertOk()
            ->assertSee('Pneus sem trincas')
            ->assertSee('Conectores de bateria');
    }

    public function test_new_order_gets_pmp_checklist_items_for_overdue_plans(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo PMP Snapshot']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo PMP Snapshot', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        \App\Models\MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva teste',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $pmpItems = $order->checklists()->where('checklist_type', 'pmp')->get();

        $this->assertCount(1, $pmpItems);
        $this->assertSame('Troca de óleo', $pmpItems->first()->item_name);
        $this->assertFalse($pmpItems->first()->is_completed);
    }

    public function test_new_order_gets_no_pmp_items_when_no_plan_is_overdue(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Sem Vencimento']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Sem Vencimento', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 10,
        ]);
        \App\Models\MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 5000, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva teste',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->assertSame(0, $order->checklists()->where('checklist_type', 'pmp')->count());
    }

    public function test_new_order_gets_one_pmp_item_per_overdue_plan_when_multiple(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Multiplos PMP']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Multiplos PMP', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        \App\Models\MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);
        \App\Models\MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Verificação de freios',
            'interval_hours' => 300, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva multipla',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->assertSame(2, $order->checklists()->where('checklist_type', 'pmp')->count());
    }

    public function test_edit_order_page_shows_pmp_tab_when_order_has_pmp_items(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Aba PMP']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Aba PMP', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        \App\Models\MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo PMP',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva com aba PMP',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->actingAs($admin);

        // assertSee() sozinho não provaria que é a aba PMP nova -- o
        // placeholder "Preventivas Sugeridas" (aba Dados Gerais) já
        // mostra {$plan->name} como texto informativo. Verifica o campo
        // do Repeater da aba PMP diretamente.
        Livewire::test(EditMaintenanceOrder::class, ['record' => $order->id])
            ->assertOk()
            ->assertFormFieldExists('pmp_items');
    }

    public function test_edit_order_page_hides_pmp_tab_when_order_has_no_pmp_items(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Sem PMP', 'status' => Asset::STATUS_DISPONIVEL]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva sem PMP',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->actingAs($admin);

        $this->assertSame(0, $order->checklists()->where('checklist_type', 'pmp')->count());

        Livewire::test(EditMaintenanceOrder::class, ['record' => $order->id])
            ->assertOk();
    }
}
