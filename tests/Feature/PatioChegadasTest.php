<?php

namespace Tests\Feature;

use App\Filament\Pages\PatioChegadas;
use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PatioChegadasTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(array $features = ['tabela_equipment_movements']): array
    {
        $plan = Plan::create([
            'name' => 'Plano Patio Chegadas '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Patio Chegadas '.uniqid(), 'slug' => 'tenant-patio-chegadas-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function makeConcludedDesmobilizacao(Tenant $tenant, User $admin): EquipmentMovement
    {
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Retorno', 'status' => Asset::STATUS_AGUARDANDO_TRIAGEM]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Retorno', 'maintenance_type' => MaintenanceOrder::TYPE_CHECKIN,
        ]);

        return EquipmentMovement::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id, 'asset_id' => $asset->id,
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO, 'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'completed_at' => now(),
        ]);
    }

    public function test_pending_list_shows_concluded_desmobilizations_without_arrival(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $movement = $this->makeConcludedDesmobilizacao($tenant, $admin);
        $this->actingAs($admin);

        $pending = Livewire::test(PatioChegadas::class)->instance()->pending;

        $this->assertTrue($pending->contains('id', $movement->id));
    }

    public function test_mobilizacao_movements_never_appear_in_pending_list(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Mobilização', 'status' => Asset::STATUS_DISPONIVEL]);
        EquipmentMovement::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO, 'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'completed_at' => now(),
        ]);
        $this->actingAs($admin);

        $pending = Livewire::test(PatioChegadas::class)->instance()->pending;

        $this->assertCount(0, $pending);
    }

    public function test_registering_arrival_creates_event_and_releases_asset(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $movement = $this->makeConcludedDesmobilizacao($tenant, $admin);
        $this->actingAs($admin);

        Livewire::test(PatioChegadas::class)
            ->set('conditionNotes', 'Sem avarias aparentes.')
            ->call('registerArrival', $movement->id);

        $movement->refresh();
        $this->assertNotNull($movement->patioArrival);
        $this->assertSame($admin->id, $movement->patioArrival->confirmed_by_user_id);
        $this->assertSame('Sem avarias aparentes.', $movement->patioArrival->initial_condition_notes);
        $this->assertSame(Asset::STATUS_DISPONIVEL, $movement->asset->fresh()->status);
    }

    public function test_registering_arrival_twice_for_the_same_movement_fails(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $movement = $this->makeConcludedDesmobilizacao($tenant, $admin);
        $this->actingAs($admin);

        Livewire::test(PatioChegadas::class)->call('registerArrival', $movement->id);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(PatioChegadas::class)->call('registerArrival', $movement->id);
    }

    public function test_page_is_blocked_when_plan_lacks_equipment_movements_feature(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']);
        $this->actingAs($admin);

        $this->get(PatioChegadas::getUrl(['tenant' => $tenant->slug]))->assertForbidden();
    }
}
