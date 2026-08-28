<?php

namespace Tests\Feature;

use App\Filament\Pages\ConsultaClientePmp;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Responde a pergunta original do usuário 2026-08-26 ("tenho N máquinas no
 * cliente X, qual manutenção o técnico deve fazer lá"). Client não tem
 * client_id direto em Asset -- vínculo é via Contract ativo, isolamento
 * entre clientes é o ponto crítico a testar.
 */
class ConsultaClientePmpTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Consulta '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_maintenance_plans', 'tabela_clients', 'tabela_contracts'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Consulta '.uniqid(), 'slug' => 'tenant-consulta-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Consulta', 'email' => 'admin-consulta-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeClientWithAsset(Tenant $tenant, string $label): array
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente '.$label]);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo '.$label,
            'status' => Asset::STATUS_LOCADO, 'horimetro_atual' => 0,
        ]);
        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.$label.'-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000, 'is_active' => true,
        ]);

        return [$client, $asset];
    }

    public function test_overdue_plan_appears_in_atrasada_category(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$client, $asset] = $this->makeClientWithAsset($tenant, 'A');

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Revisão vencida',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)
            ->set('clientId', $client->id);

        $rows = $page->instance()->maintenanceRows;

        $this->assertCount(1, $rows);
        $this->assertSame('atrasada', $rows->first()['category']);
        $this->assertSame('Ativo A', $rows->first()['asset']->name);
    }

    public function test_asset_of_another_client_never_appears(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$clientA, $assetA] = $this->makeClientWithAsset($tenant, 'A');
        [$clientB, $assetB] = $this->makeClientWithAsset($tenant, 'B');

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetA->id, 'name' => 'Plano A',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetB->id, 'name' => 'Plano B',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)
            ->set('clientId', $clientA->id);

        $rows = $page->instance()->maintenanceRows;

        $this->assertCount(1, $rows);
        $this->assertSame('Ativo A', $rows->first()['asset']->name);
    }

    public function test_order_in_progress_appears_in_em_andamento_category(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$client, $asset] = $this->makeClientWithAsset($tenant, 'C');

        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano em andamento',
            'interval_days' => 30, 'last_service_date' => now(),
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plan->id,
            'description' => 'OS em andamento', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'em_manutencao', 'status' => 'Em Andamento',
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)
            ->set('clientId', $client->id);

        $rows = $page->instance()->maintenanceRows;

        $this->assertCount(1, $rows);
        $this->assertSame('em_andamento', $rows->first()['category']);
    }

    public function test_asset_with_inactive_contract_does_not_appear(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Inativo']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Devolvido', 'status' => Asset::STATUS_DISPONIVEL]);
        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-INATIVO-'.uniqid(), 'start_date' => now()->subMonths(6),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000, 'is_active' => false,
        ]);

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano ativo devolvido',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)
            ->set('clientId', $client->id);

        $this->assertCount(0, $page->instance()->maintenanceRows);
    }

    /**
     * Pedido do usuário 2026-08-28: filtros de Equipamento/Status/Técnico
     * ao lado do seletor de cliente, tudo numa tabela única (estilo
     * planilha) em vez de seções separadas por status.
     */
    public function test_asset_filter_restricts_to_selected_asset(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$client, $assetA] = $this->makeClientWithAsset($tenant, 'FiltroA');
        $assetB = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo FiltroB', 'status' => Asset::STATUS_LOCADO]);
        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $assetB->id,
            'contract_number' => 'CT-FILTRO-B-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000, 'is_active' => true,
        ]);

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetA->id, 'name' => 'Plano A',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetB->id, 'name' => 'Plano B',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)
            ->set('clientId', $client->id)
            ->set('filterAssetId', $assetB->id);

        $rows = $page->instance()->maintenanceRows;

        $this->assertCount(1, $rows);
        $this->assertSame('Ativo FiltroB', $rows->first()['asset']->name);
    }

    public function test_status_filter_restricts_to_selected_category(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$client, $asset] = $this->makeClientWithAsset($tenant, 'StatusFiltro');

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano vencido',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano em dia',
            'interval_days' => 30, 'last_service_date' => now(),
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)
            ->set('clientId', $client->id)
            ->set('filterStatus', 'atrasada');

        $rows = $page->instance()->maintenanceRows;

        $this->assertCount(1, $rows);
        $this->assertSame('Plano vencido', $rows->first()['plan']->name);
    }

    public function test_technician_filter_restricts_to_orders_from_that_technician(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$client, $asset] = $this->makeClientWithAsset($tenant, 'TecFiltro');

        $technicianA = User::create([
            'name' => 'Tecnico Consulta A', 'email' => 'tec-consulta-a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $technicianB = User::create([
            'name' => 'Tecnico Consulta B', 'email' => 'tec-consulta-b-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $planA = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano Tecnico A',
            'interval_days' => 30, 'last_service_date' => now(),
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $planA->id,
            'description' => 'OS tecnico A', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'em_manutencao', 'status' => 'Em Andamento', 'technician_id' => $technicianA->id,
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)
            ->set('clientId', $client->id)
            ->set('filterTechnicianId', $technicianB->id);

        $this->assertCount(0, $page->instance()->maintenanceRows);

        $page->set('filterTechnicianId', $technicianA->id);
        $rows = $page->instance()->maintenanceRows;

        $this->assertCount(1, $rows);
        $this->assertSame('Plano Tecnico A', $rows->first()['plan']->name);
    }
}
