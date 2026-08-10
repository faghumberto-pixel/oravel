<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\FreightCarrier;
use App\Models\FreightRecord;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gap do diagnostico de PTA: nenhuma tabela tinha tipo de veiculo exigido
 * (prancha, munck) ou seguro/apolice de transporte.
 */
class FreightVehicleTypeAndInsuranceTest extends TestCase
{
    use RefreshDatabase;

    public function test_freight_carrier_saves_vehicle_types_and_insurance_details(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Frete '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_freight_carriers'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Frete '.uniqid(), 'slug' => 'tenant-frete-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $carrier = FreightCarrier::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Transportadora Pesada Ltda',
            'vehicle_types' => [FreightCarrier::VEHICLE_PRANCHA, FreightCarrier::VEHICLE_MUNCK],
            'insurance_policy_number' => 'AP-12345',
            'insurance_coverage_value' => 500000,
        ]);

        $fresh = $carrier->fresh();

        $this->assertSame([FreightCarrier::VEHICLE_PRANCHA, FreightCarrier::VEHICLE_MUNCK], $fresh->vehicle_types);
        $this->assertSame('AP-12345', $fresh->insurance_policy_number);
        $this->assertEquals(500000, (float) $fresh->insurance_coverage_value);
    }

    public function test_freight_record_saves_vehicle_type_used_and_insurance_confirmed(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Frete Record '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_freight_carriers', 'tabela_freight_records', 'tabela_maintenance_orders', 'tabela_equipment_movements'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Frete Record '.uniqid(), 'slug' => 'tenant-frete-record-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $carrier = FreightCarrier::create(['tenant_id' => $tenant->id, 'nome' => 'Transportadora Teste']);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Plataforma Teste', 'status' => Asset::STATUS_DISPONIVEL]);
        $maintenanceOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Frete de teste', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);
        $movement = EquipmentMovement::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $maintenanceOrder->id, 'asset_id' => $asset->id,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $record = FreightRecord::create([
            'tenant_id' => $tenant->id,
            'equipment_movement_id' => $movement->id,
            'tipo' => FreightRecord::TIPO_TERCEIRIZADO,
            'freight_carrier_id' => $carrier->id,
            'valor' => 1500,
            'data' => now(),
            'vehicle_type_used' => FreightCarrier::VEHICLE_PRANCHA,
            'insurance_confirmed' => true,
        ]);

        $fresh = $record->fresh();

        $this->assertSame(FreightCarrier::VEHICLE_PRANCHA, $fresh->vehicle_type_used);
        $this->assertTrue($fresh->insurance_confirmed);
    }
}
