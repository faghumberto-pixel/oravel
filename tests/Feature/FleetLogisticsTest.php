<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementLocation;
use App\Models\EquipmentPatioArrival;
use App\Models\FleetDriver;
use App\Models\FleetDriverDocument;
use App\Models\FleetVehicle;
use App\Models\FreightCarrier;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Camada de dados do modulo de Logistica (auditoria 2026-07-11): motoristas,
 * atribuicao de veiculo/motorista a uma movimentacao com automacao de
 * FleetVehicle::status, checkpoints de localizacao (captura manual) e
 * evento formal de chegada no patio.
 */
class FleetLogisticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(array $features = ['tabela_assets', 'tabela_equipment_movements', 'tabela_fleet_vehicles', 'tabela_fleet_drivers']): array
    {
        $plan = Plan::create([
            'name' => 'Plano Logistica '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Logistica '.uniqid(), 'slug' => 'tenant-logistica-'.uniqid(),
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

    private function makeMovement(Tenant $tenant, array $overrides = []): EquipmentMovement
    {
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Teste', 'status' => Asset::STATUS_DISPONIVEL]);

        return EquipmentMovement::create(array_merge([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
            'status' => EquipmentMovement::STATUS_AGUARDANDO_VISTORIA,
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // FleetDriver: cadastro + documentacao
    // ---------------------------------------------------------------

    public function test_fleet_driver_can_be_created_with_cnh_data(): void
    {
        [$tenant] = $this->makeTenant();

        $driver = FleetDriver::create([
            'tenant_id' => $tenant->id,
            'name' => 'João Motorista',
            'cpf' => '123.456.789-00',
            'employment_type' => FleetDriver::EMPLOYMENT_PROPRIO,
            'cnh_number' => '12345678900',
            'cnh_category' => 'E',
            'cnh_expiry_date' => now()->addYear(),
            'active' => true,
        ]);

        $this->assertFalse($driver->isCnhVencida());
        $this->assertFalse($driver->isCnhProximoVencimento());
    }

    public function test_fleet_driver_cnh_expiry_alerts_mirror_fleet_vehicle_document_pattern(): void
    {
        [$tenant] = $this->makeTenant();

        $vencida = FleetDriver::create([
            'tenant_id' => $tenant->id, 'name' => 'CNH Vencida',
            'cnh_expiry_date' => now()->subDay(),
        ]);
        $proximoVencimento = FleetDriver::create([
            'tenant_id' => $tenant->id, 'name' => 'CNH Proximo Vencimento',
            'cnh_expiry_date' => now()->addDays(10),
        ]);
        $emDia = FleetDriver::create([
            'tenant_id' => $tenant->id, 'name' => 'CNH Em Dia',
            'cnh_expiry_date' => now()->addYear(),
        ]);

        $this->assertTrue($vencida->isCnhVencida());
        $this->assertFalse($vencida->isCnhProximoVencimento());

        $this->assertFalse($proximoVencimento->isCnhVencida());
        $this->assertTrue($proximoVencimento->isCnhProximoVencimento());

        $this->assertFalse($emDia->isCnhVencida());
        $this->assertFalse($emDia->isCnhProximoVencimento());
    }

    public function test_fleet_driver_can_be_linked_to_a_freight_carrier_when_terceiro(): void
    {
        [$tenant] = $this->makeTenant();

        $carrier = FreightCarrier::create(['tenant_id' => $tenant->id, 'nome' => 'Transportadora X']);
        $driver = FleetDriver::create([
            'tenant_id' => $tenant->id, 'name' => 'Motorista Terceiro',
            'employment_type' => FleetDriver::EMPLOYMENT_TERCEIRO,
            'freight_carrier_id' => $carrier->id,
        ]);

        $this->assertTrue($driver->freightCarrier->is($carrier));
    }

    public function test_fleet_driver_vehicle_pivot_tracks_which_vehicles_a_driver_is_authorized_for(): void
    {
        [$tenant] = $this->makeTenant();

        $driver = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'Motorista Carreta']);
        $carreta = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'AAA1111', 'modelo' => 'Carreta', 'tipo' => 'carreta']);
        $caminhao = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'BBB2222', 'modelo' => 'Caminhão', 'tipo' => 'caminhao']);

        $driver->vehicles()->attach([$carreta->id, $caminhao->id]);

        $this->assertCount(2, $driver->vehicles);
        $this->assertTrue($carreta->drivers->contains($driver));
    }

    public function test_fleet_driver_document_expiry_helpers_for_mopp_and_other_docs(): void
    {
        [$tenant] = $this->makeTenant();
        $driver = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'Motorista MOPP']);

        $mopp = FleetDriverDocument::create([
            'tenant_id' => $tenant->id, 'fleet_driver_id' => $driver->id,
            'tipo' => FleetDriverDocument::TIPO_MOPP, 'data_vencimento' => now()->addDays(5),
        ]);

        $this->assertTrue($mopp->isProximoVencimento());
        $this->assertFalse($mopp->isVencido());
        $this->assertTrue($driver->documents->contains($mopp));
    }

    public function test_non_admin_without_permission_is_blocked_from_fleet_driver_module(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem tabela_fleet_drivers
        $this->actingAs($admin);

        $this->assertFalse($admin->can('viewAny', FleetDriver::class));
    }

    // ---------------------------------------------------------------
    // Atribuicao de veiculo/motorista + automacao de FleetVehicle::status
    // ---------------------------------------------------------------

    public function test_assigning_a_fleet_vehicle_to_a_movement_marks_it_em_rota(): void
    {
        [$tenant] = $this->makeTenant();
        $vehicle = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'CCC3333', 'modelo' => 'Truck', 'tipo' => 'truck']);

        $this->makeMovement($tenant, ['fleet_vehicle_id' => $vehicle->id]);

        $this->assertSame(FleetVehicle::STATUS_EM_ROTA, $vehicle->fresh()->status);
    }

    public function test_concluding_the_only_movement_using_a_vehicle_releases_it_back_to_disponivel(): void
    {
        [$tenant] = $this->makeTenant();
        $vehicle = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'DDD4444', 'modelo' => 'Truck', 'tipo' => 'truck']);

        $movement = $this->makeMovement($tenant, ['fleet_vehicle_id' => $vehicle->id]);
        $this->assertSame(FleetVehicle::STATUS_EM_ROTA, $vehicle->fresh()->status);

        $movement->update(['status' => EquipmentMovement::STATUS_CONCLUIDO]);

        $this->assertSame(FleetVehicle::STATUS_DISPONIVEL, $vehicle->fresh()->status);
    }

    public function test_vehicle_stays_em_rota_if_another_active_movement_still_uses_it(): void
    {
        [$tenant] = $this->makeTenant();
        $vehicle = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'EEE5555', 'modelo' => 'Truck', 'tipo' => 'truck']);

        $movementA = $this->makeMovement($tenant, ['fleet_vehicle_id' => $vehicle->id]);
        $movementB = $this->makeMovement($tenant, ['fleet_vehicle_id' => $vehicle->id]);

        $movementA->update(['status' => EquipmentMovement::STATUS_CONCLUIDO]);

        $this->assertSame(FleetVehicle::STATUS_EM_ROTA, $vehicle->fresh()->status, 'movementB ainda esta ativa, veiculo nao deveria voltar a disponivel');

        $movementB->update(['status' => EquipmentMovement::STATUS_CONCLUIDO]);

        $this->assertSame(FleetVehicle::STATUS_DISPONIVEL, $vehicle->fresh()->status);
    }

    public function test_vehicle_already_in_manutencao_is_not_silently_flipped_to_em_rota(): void
    {
        [$tenant] = $this->makeTenant();
        $vehicle = FleetVehicle::create([
            'tenant_id' => $tenant->id, 'placa' => 'FFF6666', 'modelo' => 'Truck', 'tipo' => 'truck',
            'status' => FleetVehicle::STATUS_MANUTENCAO,
        ]);

        $this->makeMovement($tenant, ['fleet_vehicle_id' => $vehicle->id]);

        $this->assertSame(FleetVehicle::STATUS_MANUTENCAO, $vehicle->fresh()->status, 'veiculo em manutencao nao deveria virar em_rota automaticamente');
    }

    public function test_reassigning_a_movement_to_a_different_vehicle_releases_the_previous_one(): void
    {
        [$tenant] = $this->makeTenant();
        $vehicleA = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'GGG7777', 'modelo' => 'Truck', 'tipo' => 'truck']);
        $vehicleB = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'HHH8888', 'modelo' => 'Truck', 'tipo' => 'truck']);

        $movement = $this->makeMovement($tenant, ['fleet_vehicle_id' => $vehicleA->id]);
        $this->assertSame(FleetVehicle::STATUS_EM_ROTA, $vehicleA->fresh()->status);

        $movement->update(['fleet_vehicle_id' => $vehicleB->id]);

        $this->assertSame(FleetVehicle::STATUS_DISPONIVEL, $vehicleA->fresh()->status);
        $this->assertSame(FleetVehicle::STATUS_EM_ROTA, $vehicleB->fresh()->status);
    }

    public function test_is_currently_available_reflects_status_field(): void
    {
        [$tenant] = $this->makeTenant();
        $vehicle = FleetVehicle::create([
            'tenant_id' => $tenant->id, 'placa' => 'III9999', 'modelo' => 'Truck', 'tipo' => 'truck',
            'status' => FleetVehicle::STATUS_DISPONIVEL,
        ]);

        $this->assertTrue($vehicle->isCurrentlyAvailable());

        $vehicle->update(['status' => FleetVehicle::STATUS_EM_ROTA]);

        $this->assertFalse($vehicle->fresh()->isCurrentlyAvailable());
    }

    // ---------------------------------------------------------------
    // Checkpoints de localizacao (captura manual)
    // ---------------------------------------------------------------

    public function test_location_checkpoint_reverse_geocodes_address_on_creation(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Rua Teste, 123, Campinas - SP'], 200),
        ]);

        [$tenant, $admin] = $this->makeTenant();
        $movement = $this->makeMovement($tenant);

        $location = EquipmentMovementLocation::create([
            'tenant_id' => $tenant->id,
            'equipment_movement_id' => $movement->id,
            'checkpoint_type' => EquipmentMovementLocation::CHECKPOINT_SAIDA_PATIO,
            'latitude' => -22.9056,
            'longitude' => -47.0608,
            'captured_at' => now(),
            'captured_by_user_id' => $admin->id,
        ]);

        $this->assertSame('Rua Teste, 123, Campinas - SP', $location->address);
    }

    public function test_movement_locations_are_returned_in_chronological_order_for_route_plotting(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['display_name' => 'Endereço'], 200)]);

        [$tenant, $admin] = $this->makeTenant();
        $movement = $this->makeMovement($tenant);

        $chegada = EquipmentMovementLocation::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
            'checkpoint_type' => EquipmentMovementLocation::CHECKPOINT_CHEGADA_DESTINO,
            'latitude' => -22.90, 'longitude' => -47.06,
            'captured_at' => now()->addHours(3), 'captured_by_user_id' => $admin->id,
        ]);
        $saida = EquipmentMovementLocation::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
            'checkpoint_type' => EquipmentMovementLocation::CHECKPOINT_SAIDA_PATIO,
            'latitude' => -22.91, 'longitude' => -47.07,
            'captured_at' => now(), 'captured_by_user_id' => $admin->id,
        ]);

        $ordered = $movement->locations()->pluck('id')->all();

        $this->assertSame([$saida->id, $chegada->id], $ordered);
    }

    // ---------------------------------------------------------------
    // Chegada formal no patio -- historico auditavel
    // ---------------------------------------------------------------

    public function test_patio_arrival_registers_a_formal_event_distinct_from_checklist_completion(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $movement = $this->makeMovement($tenant, [
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO,
            'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'completed_at' => now()->subHour(),
        ]);

        $this->assertNull($movement->patioArrival, 'checklist concluido nao deveria por si so criar a chegada no patio');

        $arrival = EquipmentPatioArrival::create([
            'tenant_id' => $tenant->id,
            'equipment_movement_id' => $movement->id,
            'arrived_at' => now(),
            'confirmed_by_user_id' => $admin->id,
            'initial_condition_notes' => 'Sem avarias aparentes.',
        ]);

        $this->assertTrue($movement->fresh()->patioArrival->is($arrival));
    }

    public function test_a_movement_can_only_have_one_patio_arrival(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $movement = $this->makeMovement($tenant, ['type' => EquipmentMovement::TYPE_DESMOBILIZACAO]);

        EquipmentPatioArrival::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
            'arrived_at' => now(), 'confirmed_by_user_id' => $admin->id,
        ]);

        $this->expectException(QueryException::class);

        EquipmentPatioArrival::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
            'arrived_at' => now(), 'confirmed_by_user_id' => $admin->id,
        ]);
    }

    public function test_asset_patio_history_can_be_queried_across_all_its_movements(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Histórico', 'status' => Asset::STATUS_DISPONIVEL]);

        $idaEVolta1 = EquipmentMovement::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO, 'status' => EquipmentMovement::STATUS_CONCLUIDO,
        ]);
        EquipmentPatioArrival::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $idaEVolta1->id,
            'arrived_at' => now()->subDays(10), 'confirmed_by_user_id' => $admin->id,
        ]);

        $idaEVolta2 = EquipmentMovement::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO, 'status' => EquipmentMovement::STATUS_CONCLUIDO,
        ]);
        EquipmentPatioArrival::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $idaEVolta2->id,
            'arrived_at' => now()->subDay(), 'confirmed_by_user_id' => $admin->id,
        ]);

        $historico = EquipmentMovement::where('asset_id', $asset->id)
            ->whereHas('patioArrival')
            ->with('patioArrival')
            ->get();

        $this->assertCount(2, $historico);
    }
}
