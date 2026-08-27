<?php

namespace Tests\Feature;

use App\Console\Commands\CheckMaintenanceDueAlerts;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-27: OS gerada automaticamente pelo vencimento
 * de um plano de PMP precisa deixar claro que é PMP do grupo -- origin
 * estruturado + descrição com prefixo "PMP · {grupo}".
 */
class AutoCreatedOrderHasClearPmpOriginTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Origin '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_plans', 'tabela_assets', 'tabela_checklist_groups'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Origin '.uniqid(), 'slug' => 'tenant-origin-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    public function test_auto_created_order_from_group_plan_has_pmp_origin_and_group_name_in_description(): void
    {
        $tenant = $this->makeTenant();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras Retráteis']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Origin', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'auto_create_order' => true,
        ]);

        $this->artisan(CheckMaintenanceDueAlerts::class)->assertSuccessful();

        $order = MaintenanceOrder::where('tenant_id', $tenant->id)->sole();

        $this->assertSame('pmp_auto', $order->origin);
        $this->assertStringContainsString('PMP · Empilhadeiras Retráteis', $order->description);
        $this->assertNull($order->technician_id);
        $this->assertSame('Aberto', $order->status);
    }

    public function test_manually_created_order_has_no_origin(): void
    {
        $tenant = $this->makeTenant();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Manual', 'status' => Asset::STATUS_DISPONIVEL]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Aberta manualmente',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->assertNull($order->origin);
    }

    public function test_auto_created_order_from_asset_plan_without_group_has_no_prefix(): void
    {
        $tenant = $this->makeTenant();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Sem Grupo', 'status' => Asset::STATUS_DISPONIVEL,
            'horimetro_atual' => 500,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Revisão avulsa',
            'interval_hours' => 250, 'last_service_hours' => 0, 'auto_create_order' => true,
        ]);

        $this->artisan(CheckMaintenanceDueAlerts::class)->assertSuccessful();

        $order = MaintenanceOrder::where('tenant_id', $tenant->id)->sole();

        $this->assertSame('pmp_auto', $order->origin);
        $this->assertStringNotContainsString('PMP ·', $order->description);
    }
}
