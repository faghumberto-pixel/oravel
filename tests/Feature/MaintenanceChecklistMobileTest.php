<?php

namespace Tests\Feature;

use App\Livewire\MaintenanceChecklistMobile;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MaintenanceChecklistMobileTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithUser(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Checklist '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Checklist '.uniqid(),
            'slug' => 'tenant-checklist-'.uniqid(),
            'status' => 'active',
            'plan_id' => $plan->id,
        ]);

        $user = User::create([
            'name' => 'Tecnico Campo',
            'email' => 'tecnico-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'),
            'tenant_id' => $tenant->id,
        ]);
        // email_verified_at nao esta no $fillable de User (create() acima o
        // ignoraria silenciosamente) -- setado a parte, mesmo padrao do
        // App\Services\TenantProvisioner::provision().
        $user->forceFill(['email_verified_at' => now()])->save();

        Permission::firstOrCreate(['name' => 'ler_ordem_servico', 'guard_name' => 'web']);
        $user->givePermissionTo('ler_ordem_servico');

        return [$tenant, $user];
    }

    private function makeOrderWithItems(User $user, int $itemCount = 2): MaintenanceOrder
    {
        $this->actingAs($user);

        $asset = Asset::create([
            'name' => 'Gerador Diesel',
            'tag' => 'AST-'.uniqid(),
            'status' => 'ativo',
        ]);

        $order = MaintenanceOrder::create([
            'asset_id' => $asset->id,
            'technician_id' => $user->id,
            'description' => 'Inspecao de rotina',
            'maintenance_type' => MaintenanceOrder::TYPE_CHECKIN,
        ]);

        for ($i = 0; $i < $itemCount; $i++) {
            MaintenanceOrderChecklist::create([
                'maintenance_order_id' => $order->id,
                'category' => 'Motor',
                'item_name' => "Item {$i}",
                'is_completed' => false,
            ]);
        }

        return $order->fresh();
    }

    public function test_user_of_same_tenant_can_view_checklist_items(): void
    {
        [$tenant, $user] = $this->makeTenantWithUser();
        $order = $this->makeOrderWithItems($user);

        $this->actingAs($user)
            ->get(route('maintenance-orders.checklist-mobile', $order))
            ->assertOk()
            ->assertSee('Item 0')
            ->assertSee('Item 1');
    }

    public function test_user_of_another_tenant_cannot_view_the_order(): void
    {
        [$tenantA, $userA] = $this->makeTenantWithUser();
        $order = $this->makeOrderWithItems($userA);

        [$tenantB, $userB] = $this->makeTenantWithUser();

        $this->actingAs($userB)
            ->get(route('maintenance-orders.checklist-mobile', $order))
            ->assertNotFound();
    }

    public function test_toggling_an_item_persists_completion_and_updates_progress(): void
    {
        [$tenant, $user] = $this->makeTenantWithUser();
        $order = $this->makeOrderWithItems($user, 2);
        $item = $order->checklists()->first();

        Livewire::actingAs($user)
            ->test(MaintenanceChecklistMobile::class, ['maintenanceOrder' => $order])
            ->assertSet('progress', 0)
            ->call('toggleComplete', $item->id)
            ->assertSet('progress', 50);

        $this->assertTrue($item->fresh()->is_completed);
    }

    public function test_finalize_is_blocked_until_all_items_completed(): void
    {
        [$tenant, $user] = $this->makeTenantWithUser();
        $order = $this->makeOrderWithItems($user, 2);
        $items = $order->checklists;

        $component = Livewire::actingAs($user)
            ->test(MaintenanceChecklistMobile::class, ['maintenanceOrder' => $order])
            ->call('finalize')
            ->assertNoRedirect();

        foreach ($items as $item) {
            $component->call('toggleComplete', $item->id);
        }

        $component->call('finalize')
            ->assertRedirect(route('filament.admin.resources.maintenance-orders.edit', ['record' => $order]));
    }
}
