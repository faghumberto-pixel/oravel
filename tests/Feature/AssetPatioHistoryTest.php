<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\RelationManagers\PatioArrivalsRelationManager;
use App\Models\Asset;
use App\Models\Client;
use App\Models\EquipmentMovement;
use App\Models\EquipmentPatioArrival;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetPatioHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Historico Patio '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders', 'tabela_equipment_movements'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Historico Patio '.uniqid(), 'slug' => 'tenant-hist-patio-'.uniqid(),
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

    public function test_asset_patio_arrivals_relation_aggregates_across_all_its_movements(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Histórico']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Idas e Vindas', 'status' => Asset::STATUS_DISPONIVEL]);

        foreach ([now()->subDays(30), now()->subDays(10)] as $when) {
            $order = MaintenanceOrder::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
                'description' => 'Retorno', 'maintenance_type' => MaintenanceOrder::TYPE_CHECKIN, 'client_id' => $client->id,
            ]);
            $movement = EquipmentMovement::create([
                'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id, 'asset_id' => $asset->id,
                'type' => EquipmentMovement::TYPE_DESMOBILIZACAO, 'status' => EquipmentMovement::STATUS_CONCLUIDO,
                'completed_at' => $when,
            ]);
            EquipmentPatioArrival::create([
                'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
                'arrived_at' => $when->clone()->addHours(3), 'confirmed_by_user_id' => $admin->id,
            ]);
        }

        $this->assertCount(2, $asset->patioArrivals);
    }

    public function test_relation_manager_lists_patio_arrival_history_on_asset_edit_page(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Relation Manager', 'status' => Asset::STATUS_DISPONIVEL]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Retorno', 'maintenance_type' => MaintenanceOrder::TYPE_CHECKIN,
        ]);
        $movement = EquipmentMovement::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id, 'asset_id' => $asset->id,
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO, 'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'completed_at' => now()->subDay(),
        ]);
        EquipmentPatioArrival::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
            'arrived_at' => now(), 'confirmed_by_user_id' => $admin->id,
            'initial_condition_notes' => 'Chegou com pequeno amassado no para-choque.',
        ]);

        $this->actingAs($admin);

        Livewire::test(PatioArrivalsRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass' => EditAsset::class,
        ])->assertSee('Chegou com pequeno amassado no para-choque.');
    }
}
