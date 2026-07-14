<?php

namespace Tests\Feature;

use App\Filament\Pages\PatioChegadas;
use App\Livewire\EquipmentPatioArrivalMobile;
use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItemTemplate;
use App\Models\EquipmentPatioArrival;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
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

    public function test_finishing_laudo_de_recebimento_creates_event_and_releases_asset(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        EquipmentMovementItemTemplate::create([
            'type' => EquipmentPatioArrival::TEMPLATE_TYPE, 'section' => 'Geral',
            'label' => 'Inspeção visual', 'sort_order' => 1, 'requires_photo' => false,
        ]);
        $movement = $this->makeConcludedDesmobilizacao($tenant, $admin);
        $this->actingAs($admin);

        $component = Livewire::test(EquipmentPatioArrivalMobile::class, ['equipmentMovement' => $movement])
            ->set('generalNotes', 'Sem avarias aparentes.')
            ->call('saveGeneralNotes');

        $item = $component->instance()->patioArrival->items()->first();
        $component->call('toggleItem', $item->id)
            ->assertSet('progress', 100)
            ->call('finalize');

        $movement->refresh();
        $this->assertNotNull($movement->patioArrival);
        $this->assertNotNull($movement->patioArrival->completed_at);
        $this->assertSame($admin->id, $movement->patioArrival->confirmed_by_user_id);
        $this->assertSame('Sem avarias aparentes.', $movement->patioArrival->initial_condition_notes);
        $this->assertSame(Asset::STATUS_DISPONIVEL, $movement->asset->fresh()->status);
    }

    public function test_reopening_laudo_de_recebimento_reuses_the_same_draft(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $movement = $this->makeConcludedDesmobilizacao($tenant, $admin);
        $this->actingAs($admin);

        $first = Livewire::test(EquipmentPatioArrivalMobile::class, ['equipmentMovement' => $movement])
            ->instance()->patioArrival;

        $second = Livewire::test(EquipmentPatioArrivalMobile::class, ['equipmentMovement' => $movement])
            ->instance()->patioArrival;

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EquipmentPatioArrival::where('equipment_movement_id', $movement->id)->count());
    }

    public function test_page_is_blocked_when_plan_lacks_equipment_movements_feature(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']);
        $this->actingAs($admin);

        $this->get(PatioChegadas::getUrl(['tenant' => $tenant->slug]))->assertForbidden();
    }
}
