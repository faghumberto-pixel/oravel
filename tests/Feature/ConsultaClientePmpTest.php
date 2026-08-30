<?php

namespace Tests\Feature;

use App\Filament\Pages\ConsultaClientePmp;
use App\Models\Asset;
use App\Models\ChecklistGroup;
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

    /**
     * Pedido do usuário 2026-08-30: gestor da Eletraq não conseguia
     * estabelecer referência de grupo nesta tela nem tomar providência
     * (abrir OS) direto na linha atrasada, e faltava o botão Imprimir que
     * as outras telas de PMP já têm.
     */
    public function test_group_filter_restricts_to_selected_group(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Grupo']);
        $groupA = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);
        $groupB = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Plataformas']);

        $assetA = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira 1', 'status' => Asset::STATUS_LOCADO, 'checklist_group_id' => $groupA->id]);
        $assetB = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Plataforma 1', 'status' => Asset::STATUS_LOCADO, 'checklist_group_id' => $groupB->id]);

        foreach ([$assetA, $assetB] as $i => $asset) {
            Contract::create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
                'contract_number' => 'CT-GRUPO-'.$i.'-'.uniqid(), 'start_date' => now(),
                'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000, 'is_active' => true,
            ]);
        }

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetA->id, 'name' => 'Plano Empilhadeira',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetB->id, 'name' => 'Plano Plataforma',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)
            ->set('clientId', $client->id)
            ->set('filterGroupId', $groupA->id);

        $rows = $page->instance()->maintenanceRows;

        $this->assertCount(1, $rows);
        $this->assertSame('Empilhadeira 1', $rows->first()['asset']->name);
    }

    public function test_group_filter_options_only_include_groups_of_that_client(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Grupo Opcoes']);
        $groupDoCliente = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Do Cliente']);
        $groupDeOutro = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo De Outro Tenant Asset']);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Com Grupo', 'status' => Asset::STATUS_LOCADO, 'checklist_group_id' => $groupDoCliente->id]);
        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-OPCOES-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000, 'is_active' => true,
        ]);

        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Sem Contrato Com Este Cliente', 'status' => Asset::STATUS_DISPONIVEL, 'checklist_group_id' => $groupDeOutro->id]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)->set('clientId', $client->id);

        $options = $page->instance()->filterGroupOptions;

        $this->assertTrue($options->has($groupDoCliente->id));
        $this->assertFalse($options->has($groupDeOutro->id));
    }

    public function test_abrir_os_action_creates_new_order_when_none_exists(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$client, $asset] = $this->makeClientWithAsset($tenant, 'AbrirOs');
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano Sem OS',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)->set('clientId', $client->id);
        $row = $page->instance()->maintenanceRows->first();

        $this->assertNull($row['order']);

        $orderId = $page->instance()->resolveOrCreateOrder($asset->id, $plan->id);

        $order = MaintenanceOrder::findOrFail($orderId);
        $this->assertSame($asset->id, $order->asset_id);
        $this->assertSame($plan->id, $order->maintenance_plan_id);
        $this->assertSame(MaintenanceOrder::TYPE_PREVENTIVE, $order->maintenance_type);
    }

    public function test_abrir_os_action_returns_existing_order_without_duplicating(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$client, $asset] = $this->makeClientWithAsset($tenant, 'ComOs');
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano Com OS',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);
        $existingOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plan->id,
            'description' => 'OS já existente', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'aguardando_diagnostico', 'status' => 'Aberto',
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(ConsultaClientePmp::class)->set('clientId', $client->id);

        $orderId = $page->instance()->resolveOrCreateOrder($asset->id, $plan->id);

        $this->assertSame($existingOrder->id, $orderId);
        $this->assertSame(1, MaintenanceOrder::where('asset_id', $asset->id)->count());
    }

    public function test_botao_imprimir_gera_relatorio_respeitando_filtros(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$client, $asset] = $this->makeClientWithAsset($tenant, 'Imprimir');
        $asset->update(['patrimonio' => 'PAT-CLI-01']);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano Para Imprimir',
            'interval_days' => 30, 'last_service_date' => now()->subDays(60),
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(ConsultaClientePmp::class)->set('clientId', $client->id);

        $reflection = new \ReflectionMethod(ConsultaClientePmp::class, 'getHeaderActions');
        $reflection->setAccessible(true);
        $actions = $reflection->invoke($component->instance());

        $imprimir = collect($actions)->first(fn ($action) => $action->getName() === 'imprimir_consulta');
        $this->assertNotNull($imprimir);

        $url = $imprimir->getUrl();
        $this->assertNotEmpty($url);
        $this->assertStringContainsString('consulta-cliente-pmp/print', $url);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('PAT-CLI-01');
        $response->assertSee('Plano Para Imprimir');
    }
}
