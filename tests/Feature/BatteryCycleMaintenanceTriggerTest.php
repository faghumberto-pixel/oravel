<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\BatteryCycleReading;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gap do diagnostico de PTA: MaintenancePlan so' disparava por
 * horimetro/data, sem gatilho por ciclos de bateria (relevante pra
 * equipamentos eletricos). BatteryCycleReading e' o contador incremental
 * que faltava (analogo a HorimeterReading), sincronizado via
 * BatteryCycleReadingObserver.
 */
class BatteryCycleMaintenanceTriggerTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAndAsset(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Ciclo Bateria '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_plans', 'tabela_battery_cycle_readings'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Ciclo Bateria '.uniqid(), 'slug' => 'tenant-ciclo-bateria-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'PTA Elétrica', 'status' => Asset::STATUS_DISPONIVEL]);

        return [$tenant, $asset];
    }

    public function test_creating_a_battery_cycle_reading_syncs_asset_and_never_regresses(): void
    {
        [$tenant, $asset] = $this->makeTenantAndAsset();

        BatteryCycleReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'cycles' => 150, 'recorded_at' => now(),
        ]);

        $this->assertSame(150, $asset->fresh()->battery_cycles_atual);

        // Leitura menor nao regride o contador (mesmo criterio de HorimeterReading)
        BatteryCycleReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'cycles' => 100, 'recorded_at' => now(),
        ]);

        $this->assertSame(150, $asset->fresh()->battery_cycles_atual);
    }

    public function test_maintenance_plan_is_overdue_when_battery_cycles_interval_is_exceeded(): void
    {
        [$tenant, $asset] = $this->makeTenantAndAsset();

        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'name' => 'Manutenção de bateria',
            'interval_battery_cycles' => 200,
            'last_service_battery_cycles' => 0,
        ]);

        $status = $plan->dueStatusForAsset($asset->fresh());
        $this->assertFalse($status['is_overdue']);

        BatteryCycleReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'cycles' => 250, 'recorded_at' => now(),
        ]);

        $status = $plan->dueStatusForAsset($asset->fresh());
        $this->assertTrue($status['is_overdue']);
        $this->assertSame(50, $status['overdue_battery_cycles']);
    }

    public function test_maintenance_plan_without_battery_cycles_interval_ignores_that_dimension(): void
    {
        [$tenant, $asset] = $this->makeTenantAndAsset();

        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'name' => 'Manutenção só por hora',
            'interval_hours' => 500,
            'last_service_hours' => 0,
        ]);

        BatteryCycleReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'cycles' => 999999, 'recorded_at' => now(),
        ]);

        $status = $plan->dueStatusForAsset($asset->fresh());
        $this->assertSame(0, $status['overdue_battery_cycles']);
        $this->assertFalse($status['is_overdue']);
    }
}
