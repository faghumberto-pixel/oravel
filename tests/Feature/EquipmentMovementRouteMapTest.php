<?php

namespace Tests\Feature;

use App\Filament\Pages\DossieOperacional;
use App\Filament\Widgets\EquipmentMovementRouteMapWidget;
use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementLocation;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EquipmentMovementRouteMapTest extends TestCase
{
    use RefreshDatabase;

    private function makeScenario(): array
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Endereço Teste'], 200)]);

        $plan = Plan::create([
            'name' => 'Plano Rota '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Rota '.uniqid(), 'slug' => 'tenant-rota-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Rota', 'status' => Asset::STATUS_DISPONIVEL]);
        $maintenanceOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS com rota', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);
        $movement = EquipmentMovement::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $maintenanceOrder->id, 'asset_id' => $asset->id,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO, 'status' => EquipmentMovement::STATUS_EM_ANDAMENTO,
        ]);

        return [$tenant, $admin, $maintenanceOrder, $movement];
    }

    public function test_points_are_returned_in_chronological_order_with_labels(): void
    {
        [$tenant, $admin, , $movement] = $this->makeScenario();

        EquipmentMovementLocation::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
            'checkpoint_type' => EquipmentMovementLocation::CHECKPOINT_CHEGADA_DESTINO,
            'latitude' => -22.90, 'longitude' => -47.06, 'captured_at' => now()->addHours(2),
            'captured_by_user_id' => $admin->id,
        ]);
        EquipmentMovementLocation::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
            'checkpoint_type' => EquipmentMovementLocation::CHECKPOINT_SAIDA_PATIO,
            'latitude' => -22.91, 'longitude' => -47.07, 'captured_at' => now(),
            'captured_by_user_id' => $admin->id,
        ]);

        $widget = new EquipmentMovementRouteMapWidget;
        $widget->equipmentMovement = $movement->fresh(['locations']);

        $points = $widget->getPoints();

        $this->assertCount(2, $points);
        $this->assertSame('Saída do Pátio', $points[0]['label']);
        $this->assertSame('Chegada no Destino', $points[1]['label']);
    }

    public function test_dossie_operacional_page_renders_the_route_map_when_movement_has_checkpoints(): void
    {
        [$tenant, $admin, $maintenanceOrder, $movement] = $this->makeScenario();

        EquipmentMovementLocation::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
            'checkpoint_type' => EquipmentMovementLocation::CHECKPOINT_SAIDA_PATIO,
            'latitude' => -22.91, 'longitude' => -47.07, 'captured_at' => now(),
            'captured_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get(DossieOperacional::getUrl(['record' => $maintenanceOrder, 'tenant' => $tenant->slug]));

        $response->assertOk();
        $response->assertSee('Rota do Transporte');
        $response->assertSee('Saída do Pátio');
    }

    public function test_dossie_operacional_page_does_not_show_route_section_without_checkpoints(): void
    {
        [$tenant, $admin, $maintenanceOrder] = $this->makeScenario();

        $this->actingAs($admin);

        $response = $this->get(DossieOperacional::getUrl(['record' => $maintenanceOrder, 'tenant' => $tenant->slug]));

        $response->assertOk();
        $response->assertDontSee('Rota do Transporte');
    }
}
