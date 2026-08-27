<?php

namespace Tests\Feature;

use App\Console\Commands\CheckMaintenanceDueAlerts;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-27: item CRÍTICO de PMP vencido bloqueia o
 * Asset automaticamente (status -> Manutenção), revertendo pro status
 * real de antes quando resolvido -- CheckMaintenanceDueAlerts é o gatilho
 * (já rodava diário, ganhou essa responsabilidade nova).
 */
class AssetAutoBlockOnCriticalOverdueTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Bloqueio '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_plans', 'tabela_assets'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Bloqueio '.uniqid(), 'slug' => 'tenant-bloqueio-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    public function test_critical_overdue_plan_blocks_asset_status(): void
    {
        $tenant = $this->makeTenant();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Crítico', 'status' => Asset::STATUS_LOCADO,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Item crítico vencido',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60), 'is_critical' => true,
        ]);

        $this->artisan(CheckMaintenanceDueAlerts::class)->assertSuccessful();

        $asset->refresh();
        $this->assertSame(Asset::STATUS_MANUTENCAO, $asset->status);
        $this->assertNotNull($asset->blocked_by_pmp_at);
        $this->assertSame(Asset::STATUS_LOCADO, $asset->status_before_pmp_block);
    }

    public function test_non_critical_overdue_plan_does_not_block_asset(): void
    {
        $tenant = $this->makeTenant();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Não Crítico', 'status' => Asset::STATUS_LOCADO,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Item não crítico vencido',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60), 'is_critical' => false,
        ]);

        $this->artisan(CheckMaintenanceDueAlerts::class)->assertSuccessful();

        $asset->refresh();
        $this->assertSame(Asset::STATUS_LOCADO, $asset->status);
        $this->assertNull($asset->blocked_by_pmp_at);
    }

    public function test_resolving_critical_plan_reverts_asset_to_previous_status(): void
    {
        $tenant = $this->makeTenant();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Recuperado', 'status' => Asset::STATUS_LOCADO,
        ]);
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Item crítico a resolver',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60), 'is_critical' => true,
        ]);

        $this->artisan(CheckMaintenanceDueAlerts::class)->assertSuccessful();
        $asset->refresh();
        $this->assertSame(Asset::STATUS_MANUTENCAO, $asset->status);

        // Resolve o item (nova execução, empurra last_service_date pra hoje).
        $plan->update(['last_service_date' => now()]);

        $this->artisan(CheckMaintenanceDueAlerts::class)->assertSuccessful();
        $asset->refresh();

        $this->assertSame(Asset::STATUS_LOCADO, $asset->status);
        $this->assertNull($asset->blocked_by_pmp_at);
        $this->assertNull($asset->status_before_pmp_block);
    }

    public function test_asset_with_multiple_plans_stays_blocked_until_all_critical_ones_resolved(): void
    {
        $tenant = $this->makeTenant();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Multi Plano', 'status' => Asset::STATUS_DISPONIVEL,
        ]);
        $criticalA = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Crítico A',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60), 'is_critical' => true,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Crítico B',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60), 'is_critical' => true,
        ]);

        $this->artisan(CheckMaintenanceDueAlerts::class)->assertSuccessful();
        $asset->refresh();
        $this->assertSame(Asset::STATUS_MANUTENCAO, $asset->status);

        // Resolve só o A -- B ainda vencido, deve continuar bloqueado.
        $criticalA->update(['last_service_date' => now()]);
        $this->artisan(CheckMaintenanceDueAlerts::class)->assertSuccessful();
        $asset->refresh();

        $this->assertSame(Asset::STATUS_MANUTENCAO, $asset->status);
        $this->assertNotNull($asset->blocked_by_pmp_at);
    }
}
