<?php

namespace Tests\Feature;

use App\Filament\Widgets\FleetAvailabilityGaugeWidget;
use App\Filament\Widgets\MaintenanceCostChart;
use App\Filament\Widgets\MaintenanceOrdersOpenVsClosedAreaWidget;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Os 2 widgets novos do Painel de Controle (piloto do "usa os 3 tipos de
 * gráfico que não deram certo no PMP, mas agora no Dashboard geral" --
 * ver App\Support\SegmentDashboardWidgets, caso "default"): pontes entre os
 * componentes genéricos (App\Filament\Widgets\Charts\*) e o registro por
 * class-string do dashboard, que não passa props via @livewire().
 */
class DashboardChartWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Dash Charts '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders', 'modulo_dashboard'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Dash Charts '.uniqid(), 'slug' => 'tenant-dash-charts-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin Dash Charts', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_fleet_availability_gauge_computes_percentage(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A1', 'patrimonio' => 'PAT-1', 'status' => Asset::STATUS_DISPONIVEL]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A2', 'patrimonio' => 'PAT-2', 'status' => Asset::STATUS_DISPONIVEL]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A3', 'patrimonio' => 'PAT-3', 'status' => Asset::STATUS_DISPONIVEL]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A4', 'patrimonio' => 'PAT-4', 'status' => 'em_manutencao']);

        $this->actingAs($admin);

        Livewire::test(FleetAvailabilityGaugeWidget::class)
            ->assertSet('value', 75.0)
            ->assertSet('target', 70.0);
    }

    public function test_fleet_availability_gauge_is_zero_without_assets(): void
    {
        [, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(FleetAvailabilityGaugeWidget::class)
            ->assertSet('value', 0.0);
    }

    public function test_open_vs_closed_area_widget_splits_correctly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo OVC', 'patrimonio' => 'PAT-OVC', 'status' => 'disponivel']);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Aberta este mês',
            'status' => 'Aberto',
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Concluída este mês',
            'status' => 'Concluída', 'finished_at' => now(),
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(MaintenanceOrdersOpenVsClosedAreaWidget::class);

        $this->assertSame('Abertas', $component->get('seriesA')['name']);
        $this->assertSame('Concluídas', $component->get('seriesB')['name']);
        // 2 OS criadas neste mês (a "Concluída" também tem created_at=agora).
        $this->assertSame(2, $component->get('seriesA')['data'][5]);
        $this->assertSame(1, $component->get('seriesB')['data'][5]);
    }

    public function test_maintenance_cost_chart_bridges_to_line_chart_with_markers(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Custo', 'patrimonio' => 'PAT-CST', 'status' => 'disponivel']);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Com custo',
            'status' => 'Concluída', 'total_order_cost' => 1500,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(MaintenanceCostChart::class)
            ->assertSet('chartTitle', 'Custo de Manutenção por Mês');

        $this->assertSame(1500.0, $component->get('series')[0]['data'][0]);
    }
}
