<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource\Pages\ListMaintenanceOrders;
use App\Filament\Resources\MaintenanceOrderResource\Widgets\MaintenanceOrdersByTypeChart;
use App\Filament\Resources\MaintenanceOrderResource\Widgets\MaintenanceOrdersEvolutionChart;
use App\Filament\Resources\MaintenanceOrderResource\Widgets\MaintenanceOrdersStatusDonutChart;
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
 * Mesmo padrão do Dashboard PMP: gráficos (evolução mensal, status atual,
 * por tipo) acima do grid de Ordens de Serviço.
 */
class MaintenanceOrdersChartsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano MO Charts '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant MO Charts '.uniqid(), 'slug' => 'tenant-mo-charts-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_list_page_has_no_charts_only_cards(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Chart', 'status' => 'disponivel']);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'OS teste',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'em_manutencao',
        ]);

        $this->actingAs($admin);

        // Pedido do usuario 2026-11-03: a lista de OS nao tem graficos, so' cards.
        $this->get(ListMaintenanceOrders::getUrl())->assertOk();

        $widgets = (new \ReflectionMethod(ListMaintenanceOrders::class, 'getHeaderWidgets'))->invoke(new ListMaintenanceOrders);
        // Nem os 4 cards antigos (MaintenanceOrderStats): so' os cards de totais clicaveis.
        $this->assertSame([], $widgets);
        $this->assertNotContains(MaintenanceOrdersEvolutionChart::class, $widgets);
        $this->assertNotContains(MaintenanceOrdersStatusDonutChart::class, $widgets);
        $this->assertNotContains(MaintenanceOrdersByTypeChart::class, $widgets);

        // Os widgets continuam montando sozinhos (nao sao usados na lista).
        Livewire::test(MaintenanceOrdersEvolutionChart::class)->assertSee('Evolução Mensal de O.S. (Abertas vs. Concluídas)');
        Livewire::test(MaintenanceOrdersStatusDonutChart::class)->assertSee('Status das O.S. Atuais');
        Livewire::test(MaintenanceOrdersByTypeChart::class)->assertSee('O.S. por Tipo de Manutenção');
    }

    public function test_status_donut_only_counts_current_tenant_orders(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        [$tenantB] = $this->makeTenantAdmin();

        $assetA = Asset::create(['tenant_id' => $tenantA->id, 'name' => 'Ativo A', 'status' => 'disponivel']);
        $assetB = Asset::create(['tenant_id' => $tenantB->id, 'name' => 'Ativo B', 'status' => 'disponivel']);

        MaintenanceOrder::create([
            'tenant_id' => $tenantA->id, 'asset_id' => $assetA->id, 'description' => 'OS tenant A',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'concluido',
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenantB->id, 'asset_id' => $assetB->id, 'description' => 'OS tenant B',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'concluido',
        ]);

        $this->actingAs($adminA);

        $widget = new MaintenanceOrdersStatusDonutChart;
        $method = new \ReflectionMethod(MaintenanceOrdersStatusDonutChart::class, 'getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        // [Concluído, Em Andamento, Pendente, Bloqueado] -- só a OS do tenant A.
        $this->assertSame([1, 0, 0, 0], $data['datasets'][0]['data']);
    }

    public function test_by_type_chart_groups_by_maintenance_type(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Tipo', 'status' => 'disponivel']);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva 1',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva 2',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva 1',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        $this->actingAs($admin);

        $widget = new MaintenanceOrdersByTypeChart;
        $method = new \ReflectionMethod(MaintenanceOrdersByTypeChart::class, 'getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $this->assertSame(['Preventiva', 'Corretiva'], $data['labels']);
        $this->assertSame([2, 1], $data['datasets'][0]['data']);
    }
}
