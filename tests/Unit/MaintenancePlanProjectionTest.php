<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\HorimeterReading;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * projectedDueDates() responde "em qual mês vai vencer" -- diferente de
 * dueStatusForAsset(), que só responde "vencido agora ou não". Mesma regra
 * de vence-pelo-que-chegar-primeiro, sem recalcular vencimento do zero.
 */
class MaintenancePlanProjectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAndAsset(float $horimetroAtual = 0): array
    {
        $plan = Plan::create([
            'name' => 'Plano Projecao '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_plans'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Projecao '.uniqid(), 'slug' => 'tenant-projecao-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Projecao', 'status' => Asset::STATUS_DISPONIVEL,
            'horimetro_atual' => $horimetroAtual,
        ]);

        return [$tenant, $asset];
    }

    public function test_projects_due_date_by_days_within_window(): void
    {
        [$tenant, $asset] = $this->makeTenantAndAsset();

        $maintenancePlan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Revisão anual',
            'interval_days' => 60, 'last_service_date' => now()->subDays(30),
        ]);

        $projections = $maintenancePlan->projectedDueDates($asset, 3);

        $this->assertCount(1, $projections);
        $this->assertSame('Vencimento por data', $projections[0]['reason']);
    }

    public function test_projects_due_date_by_hours_using_average_usage(): void
    {
        [$tenant, $asset] = $this->makeTenantAndAsset(horimetroAtual: 100);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 0, 'recorded_at' => now()->subDays(10),
        ]);
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 100, 'recorded_at' => now(),
        ]);

        $maintenancePlan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0,
        ]);

        $projections = $maintenancePlan->projectedDueDates($asset, 6);

        $this->assertCount(1, $projections);
        $this->assertSame('Vencimento por horímetro (projetado)', $projections[0]['reason']);
    }

    public function test_already_overdue_plan_returns_current_month_only(): void
    {
        [$tenant, $asset] = $this->makeTenantAndAsset();

        $maintenancePlan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Revisão vencida',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);

        $projections = $maintenancePlan->projectedDueDates($asset, 3);

        $this->assertCount(1, $projections);
        $this->assertSame(0, $projections[0]['month_offset']);
    }

    public function test_due_date_beyond_projection_window_is_not_included(): void
    {
        [$tenant, $asset] = $this->makeTenantAndAsset();

        $maintenancePlan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Revisão distante',
            'interval_days' => 300, 'last_service_date' => now(),
        ]);

        $projections = $maintenancePlan->projectedDueDates($asset, 3);

        $this->assertCount(0, $projections);
    }
}
