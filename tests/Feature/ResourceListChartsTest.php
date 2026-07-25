<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Widgets\AssetsByCategoryChartWidget;
use App\Filament\Resources\AssetResource\Widgets\AssetsCreatedTrendWidget;
use App\Filament\Resources\AssetResource\Widgets\FleetAvailabilityGaugeChartWidget;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientResource\Widgets\ClientActiveContractGaugeWidget;
use App\Filament\Resources\ClientResource\Widgets\ClientsByNicheChartWidget;
use App\Filament\Resources\ClientResource\Widgets\ContractsStartedVsEndedAreaWidget;
use App\Filament\Resources\ClientResource\Widgets\NewClientsTrendWidget;
use App\Filament\Resources\CrmLeadResource;
use App\Filament\Resources\CrmLeadResource\Widgets\ConversionRateGaugeWidget;
use App\Filament\Resources\CrmLeadResource\Widgets\CrmLeadsCreatedTrendWidget;
use App\Filament\Resources\CrmLeadResource\Widgets\LeadsBySourceChartWidget;
use App\Filament\Resources\CrmLeadResource\Widgets\LeadsWonVsLostAreaWidget;
use App\Filament\Resources\MaterialResource;
use App\Filament\Resources\MaterialResource\Widgets\MaterialsByCategoryChartWidget;
use App\Filament\Resources\MaterialResource\Widgets\MaterialsCreatedTrendWidget;
use App\Filament\Resources\MaterialResource\Widgets\MaterialStockHealthGaugeWidget;
use App\Filament\Resources\MaterialResource\Widgets\StockEntriesVsExitsAreaWidget;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\SupplierResource\Widgets\PurchaseOrdersOpenVsReceivedAreaWidget;
use App\Filament\Resources\SupplierResource\Widgets\SupplierComplianceGaugeWidget;
use App\Filament\Resources\SupplierResource\Widgets\SuppliersCreatedTrendWidget;
use App\Filament\Resources\SupplierResource\Widgets\TopSuppliersByMaterialsChartWidget;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CrmLead;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário: "não aparece nenhum gráfico em nenhuma página do dev"
 * -- o dashboard padrão de Assets/Leads/Clients/Materials/Fornecedores
 * tinha só os 4 cards de KPI (já existiam pra Assets/Clients/Materials/
 * Fornecedores, Leads ganhou o dele também), faltava um GRÁFICO de
 * verdade em cada um. Reaproveita os componentes genéricos
 * (App\Filament\Widgets\Charts\{GaugeChart,LineChartWithMarkers}) já
 * usados no Painel de Controle.
 */
class ResourceListChartsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(array $features): array
    {
        $plan = Plan::create([
            'name' => 'Plano Charts '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Charts '.uniqid(), 'slug' => 'tenant-charts-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin Charts', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    /**
     * Pedido do usuário: "não é apenas 1 gráfico por página, são 3", depois
     * "está faltando mais um para ficar uniforme" -- 4 gráficos por
     * página agora, lado a lado (getHeaderWidgetsColumns()=4), cada
     * página abaixo confirma os 4 títulos.
     */
    public function test_assets_index_shows_all_4_charts(): void
    {
        [, $admin] = $this->makeTenantAdmin(['tabela_assets']);
        $this->actingAs($admin);

        $html = $this->get(AssetResource::getUrl('index'))->assertOk()->getContent();

        $this->assertStringContainsString('Ativos por Status', $html);
        $this->assertStringContainsString('Taxa de Disponibilidade da Frota', $html);
        $this->assertStringContainsString('Ativos Cadastrados por Mês', $html);
        $this->assertStringContainsString('Ativos por Categoria', $html);
    }

    public function test_leads_index_shows_all_4_charts(): void
    {
        [, $admin] = $this->makeTenantAdmin(['tabela_crm_leads']);
        $this->actingAs($admin);

        $html = $this->get(CrmLeadResource::getUrl('index'))->assertOk()->getContent();

        $this->assertStringContainsString('Leads Criados por Mês', $html);
        $this->assertStringContainsString('Taxa de Conversão', $html);
        $this->assertStringContainsString('Convertidos vs. Perdidos por Mês', $html);
        $this->assertStringContainsString('Leads por Origem', $html);
    }

    public function test_clients_index_shows_all_4_charts(): void
    {
        [, $admin] = $this->makeTenantAdmin(['tabela_clients']);
        $this->actingAs($admin);

        $html = $this->get(ClientResource::getUrl('index'))->assertOk()->getContent();

        $this->assertStringContainsString('Taxa de Clientes com Contrato Ativo', $html);
        $this->assertStringContainsString('Novos Clientes por Mês', $html);
        $this->assertStringContainsString('Contratos Iniciados vs. Encerrados por Mês', $html);
        $this->assertStringContainsString('Clientes por Nicho', $html);
    }

    public function test_materials_index_shows_all_4_charts(): void
    {
        [, $admin] = $this->makeTenantAdmin(['tabela_materials']);
        $this->actingAs($admin);

        $html = $this->get(MaterialResource::getUrl('index'))->assertOk()->getContent();

        $this->assertStringContainsString('Taxa de Estoque Saudável', $html);
        $this->assertStringContainsString('Materiais Cadastrados por Mês', $html);
        $this->assertStringContainsString('Entradas vs. Saídas de Estoque por Mês', $html);
        $this->assertStringContainsString('Materiais por Categoria', $html);
    }

    public function test_suppliers_index_shows_all_4_charts(): void
    {
        [, $admin] = $this->makeTenantAdmin(['tabela_suppliers']);
        $this->actingAs($admin);

        $html = $this->get(SupplierResource::getUrl('index'))->assertOk()->getContent();

        $this->assertStringContainsString('Taxa de Compliance Completo', $html);
        $this->assertStringContainsString('Fornecedores Cadastrados por Mês', $html);
        $this->assertStringContainsString('Ordens de Compra Abertas vs. Recebidas por Mês', $html);
        $this->assertStringContainsString('Top Fornecedores por Materiais Vinculados', $html);
    }

    public function test_client_active_contract_gauge_computes_correct_percentage(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_clients']);

        $clienteAtivo = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Ativo']);
        $assetAtivo = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo 1', 'patrimonio' => 'PAT-A1', 'status' => 'disponivel']);
        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $clienteAtivo->id, 'asset_id' => $assetAtivo->id,
            'contract_number' => 'CT-A', 'start_date' => now(), 'price' => 100, 'status' => 'Ativo',
        ]);

        Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Sem Contrato']);
        Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Sem Contrato 2']);
        Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Sem Contrato 3']);

        $this->actingAs($admin);

        $widget = new ClientActiveContractGaugeWidget;
        $widget->mount();

        $this->assertSame(25.0, $widget->value);
    }

    public function test_material_stock_health_gauge_computes_correct_percentage(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_materials']);

        Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-1', 'name' => 'Saudável 1', 'unit_of_measure' => 'un', 'current_stock' => 10, 'min_stock' => 5, 'unit_cost' => 10]);
        Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-2', 'name' => 'Saudável 2', 'unit_of_measure' => 'un', 'current_stock' => 10, 'min_stock' => 5, 'unit_cost' => 10]);
        Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-3', 'name' => 'Saudável 3', 'unit_of_measure' => 'un', 'current_stock' => 10, 'min_stock' => 5, 'unit_cost' => 10]);
        Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-4', 'name' => 'Abaixo', 'unit_of_measure' => 'un', 'current_stock' => 1, 'min_stock' => 5, 'unit_cost' => 10]);

        $this->actingAs($admin);

        $widget = new MaterialStockHealthGaugeWidget;
        $widget->mount();

        $this->assertSame(75.0, $widget->value);
    }

    public function test_supplier_compliance_gauge_computes_correct_percentage(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_suppliers']);

        Supplier::create([
            'tenant_id' => $tenant->id, 'name' => 'Completo',
            'compliance_ceis_cnep' => true, 'lista_trabalho_escravo' => true, 'termo_lgpd' => true,
        ]);
        Supplier::create([
            'tenant_id' => $tenant->id, 'name' => 'Incompleto',
            'compliance_ceis_cnep' => true, 'lista_trabalho_escravo' => false, 'termo_lgpd' => true,
        ]);

        $this->actingAs($admin);

        $widget = new SupplierComplianceGaugeWidget;
        $widget->mount();

        $this->assertSame(50.0, $widget->value);
    }

    public function test_crm_leads_created_trend_counts_by_month(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_crm_leads']);

        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead 1', 'stage' => CrmLead::STAGE_NOVO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead 2', 'stage' => CrmLead::STAGE_NOVO]);

        $this->actingAs($admin);

        $widget = new CrmLeadsCreatedTrendWidget;
        $widget->mount();

        $this->assertSame('Leads', $widget->series[0]['name']);
        $this->assertSame(2, $widget->series[0]['data'][5]);
    }

    public function test_fleet_availability_gauge_chart_widget_computes_percentage(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_assets']);

        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A1', 'patrimonio' => 'PAT-FG1', 'status' => Asset::STATUS_DISPONIVEL]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A2', 'patrimonio' => 'PAT-FG2', 'status' => 'em_manutencao']);

        $this->actingAs($admin);

        $widget = new FleetAvailabilityGaugeChartWidget;
        $widget->mount();

        $this->assertSame(50.0, $widget->value);
    }

    public function test_assets_created_trend_counts_by_month(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_assets']);

        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A1', 'patrimonio' => 'PAT-AT1', 'status' => 'disponivel']);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A2', 'patrimonio' => 'PAT-AT2', 'status' => 'disponivel']);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A3', 'patrimonio' => 'PAT-AT3', 'status' => 'disponivel']);

        $this->actingAs($admin);

        $widget = new AssetsCreatedTrendWidget;
        $widget->mount();

        $this->assertSame(3, $widget->series[0]['data'][5]);
    }

    public function test_conversion_rate_gauge_computes_correct_percentage(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_crm_leads']);

        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L1', 'stage' => CrmLead::STAGE_CONVERTIDO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L2', 'stage' => CrmLead::STAGE_NOVO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L3', 'stage' => CrmLead::STAGE_NOVO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L4', 'stage' => CrmLead::STAGE_NOVO]);

        $this->actingAs($admin);

        $widget = new ConversionRateGaugeWidget;
        $widget->mount();

        $this->assertSame(25.0, $widget->value);
    }

    public function test_leads_won_vs_lost_area_splits_correctly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_crm_leads']);

        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Ganho 1', 'stage' => CrmLead::STAGE_CONVERTIDO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Ganho 2', 'stage' => CrmLead::STAGE_CONVERTIDO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Perdido 1', 'stage' => CrmLead::STAGE_PERDIDO]);

        $this->actingAs($admin);

        $widget = new LeadsWonVsLostAreaWidget;
        $widget->mount();

        $this->assertSame('Convertidos', $widget->seriesA['name']);
        $this->assertSame('Perdidos', $widget->seriesB['name']);
        $this->assertSame(2, $widget->seriesA['data'][5]);
        $this->assertSame(1, $widget->seriesB['data'][5]);
    }

    public function test_new_clients_trend_counts_by_month(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_clients']);

        Client::create(['tenant_id' => $tenant->id, 'name' => 'C1']);
        Client::create(['tenant_id' => $tenant->id, 'name' => 'C2']);

        $this->actingAs($admin);

        $widget = new NewClientsTrendWidget;
        $widget->mount();

        $this->assertSame(2, $widget->series[0]['data'][5]);
    }

    public function test_contracts_started_vs_ended_area_splits_correctly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_clients']);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Contratos']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Contratos', 'patrimonio' => 'PAT-CTR', 'status' => 'disponivel']);

        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-1', 'start_date' => now(), 'price' => 100,
        ]);
        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-2', 'start_date' => now()->subMonths(3), 'end_date' => now(), 'price' => 100,
        ]);

        $this->actingAs($admin);

        $widget = new ContractsStartedVsEndedAreaWidget;
        $widget->mount();

        $this->assertSame('Iniciados', $widget->seriesA['name']);
        $this->assertSame('Encerrados', $widget->seriesB['name']);
        $this->assertSame(1, $widget->seriesA['data'][5]);
        $this->assertSame(1, $widget->seriesB['data'][5]);
    }

    public function test_materials_created_trend_counts_by_month(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_materials']);

        Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-T1', 'name' => 'M1', 'unit_of_measure' => 'un', 'current_stock' => 5, 'min_stock' => 1, 'unit_cost' => 10]);

        $this->actingAs($admin);

        $widget = new MaterialsCreatedTrendWidget;
        $widget->mount();

        $this->assertSame(1, $widget->series[0]['data'][5]);
    }

    public function test_stock_entries_vs_exits_area_splits_correctly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_materials']);

        $material = Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-MV1', 'name' => 'Material Mov', 'unit_of_measure' => 'un', 'current_stock' => 10, 'min_stock' => 1, 'unit_cost' => 10]);

        StockMovement::create([
            'tenant_id' => $tenant->id, 'material_id' => $material->id,
            'type' => StockMovement::TYPE_ENTRADA_COMPRA, 'quantity' => 15, 'balance_after' => 15,
        ]);
        StockMovement::create([
            'tenant_id' => $tenant->id, 'material_id' => $material->id,
            'type' => StockMovement::TYPE_SAIDA_CONSUMO, 'quantity' => 5, 'balance_after' => 10,
        ]);

        $this->actingAs($admin);

        $widget = new StockEntriesVsExitsAreaWidget;
        $widget->mount();

        $this->assertSame('Entradas', $widget->seriesA['name']);
        $this->assertSame('Saídas', $widget->seriesB['name']);
        $this->assertSame(15.0, $widget->seriesA['data'][5]);
        $this->assertSame(5.0, $widget->seriesB['data'][5]);
    }

    public function test_suppliers_created_trend_counts_by_month(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_suppliers']);

        Supplier::create(['tenant_id' => $tenant->id, 'name' => 'Fornecedor Novo']);

        $this->actingAs($admin);

        $widget = new SuppliersCreatedTrendWidget;
        $widget->mount();

        $this->assertSame(1, $widget->series[0]['data'][5]);
    }

    public function test_purchase_orders_open_vs_received_area_splits_correctly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_suppliers']);

        $supplier = Supplier::create(['tenant_id' => $tenant->id, 'name' => 'Fornecedor PO']);

        PurchaseOrder::create([
            'tenant_id' => $tenant->id, 'supplier_id' => $supplier->id, 'created_by_user_id' => $admin->id,
            'status' => PurchaseOrder::STATUS_ABERTA,
        ]);
        PurchaseOrder::create([
            'tenant_id' => $tenant->id, 'supplier_id' => $supplier->id, 'created_by_user_id' => $admin->id,
            'status' => PurchaseOrder::STATUS_RECEBIDA,
        ]);
        PurchaseOrder::create([
            'tenant_id' => $tenant->id, 'supplier_id' => $supplier->id, 'created_by_user_id' => $admin->id,
            'status' => PurchaseOrder::STATUS_RECEBIDA,
        ]);

        $this->actingAs($admin);

        $widget = new PurchaseOrdersOpenVsReceivedAreaWidget;
        $widget->mount();

        $this->assertSame('Abertas', $widget->seriesA['name']);
        $this->assertSame('Recebidas', $widget->seriesB['name']);
        $this->assertSame(1, $widget->seriesA['data'][5]);
        $this->assertSame(2, $widget->seriesB['data'][5]);
    }

    /**
     * @return array{datasets: array<int, array{data: array<int, int|float>}>, labels: array<int, string>}
     */
    private function chartData(ChartWidget $widget): array
    {
        $method = new \ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    public function test_assets_by_category_chart_groups_correctly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_assets']);

        $categoria = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Guindastes']);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A1', 'patrimonio' => 'PAT-BC1', 'status' => 'disponivel', 'asset_category_id' => $categoria->id]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A2', 'patrimonio' => 'PAT-BC2', 'status' => 'disponivel', 'asset_category_id' => $categoria->id]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A3', 'patrimonio' => 'PAT-BC3', 'status' => 'disponivel']);

        $this->actingAs($admin);

        $data = $this->chartData(new AssetsByCategoryChartWidget);

        $this->assertSame(['Guindastes', 'Sem Categoria'], $data['labels']);
        $this->assertSame([2, 1], $data['datasets'][0]['data']);
    }

    public function test_leads_by_source_chart_groups_correctly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_crm_leads']);

        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L1', 'source' => CrmLead::SOURCE_SITE]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L2', 'source' => CrmLead::SOURCE_SITE]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L3', 'source' => CrmLead::SOURCE_INDICACAO]);

        $this->actingAs($admin);

        $data = $this->chartData(new LeadsBySourceChartWidget);

        $this->assertContains('Site', $data['labels']);
        $this->assertContains('Indicação', $data['labels']);
        $this->assertSame(3, array_sum($data['datasets'][0]['data']));
    }

    public function test_clients_by_niche_chart_groups_correctly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_clients']);

        Client::create(['tenant_id' => $tenant->id, 'name' => 'C1', 'activity_type' => Client::NICHE_EVENTOS]);
        Client::create(['tenant_id' => $tenant->id, 'name' => 'C2', 'activity_type' => Client::NICHE_EVENTOS]);
        Client::create(['tenant_id' => $tenant->id, 'name' => 'C3']);

        $this->actingAs($admin);

        $data = $this->chartData(new ClientsByNicheChartWidget);

        $this->assertContains('Eventos', $data['labels']);
        $this->assertContains('Não Informado', $data['labels']);
        $this->assertSame(3, array_sum($data['datasets'][0]['data']));
    }

    public function test_materials_by_category_chart_groups_correctly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_materials']);

        $categoria = MaterialCategory::create(['tenant_id' => $tenant->id, 'name' => 'Filtros']);
        Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-BC1', 'name' => 'M1', 'unit_of_measure' => 'un', 'current_stock' => 1, 'min_stock' => 1, 'unit_cost' => 10, 'material_category_id' => $categoria->id]);
        Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-BC2', 'name' => 'M2', 'unit_of_measure' => 'un', 'current_stock' => 1, 'min_stock' => 1, 'unit_cost' => 10]);

        $this->actingAs($admin);

        $data = $this->chartData(new MaterialsByCategoryChartWidget);

        $this->assertSame(['Filtros', 'Sem Categoria'], $data['labels']);
        $this->assertSame([1, 1], $data['datasets'][0]['data']);
    }

    public function test_top_suppliers_by_materials_chart_ranks_correctly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(['tabela_suppliers']);

        $fornecedorTop = Supplier::create(['tenant_id' => $tenant->id, 'name' => 'Fornecedor Top']);
        $fornecedorBaixo = Supplier::create(['tenant_id' => $tenant->id, 'name' => 'Fornecedor Baixo']);

        Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-SUP1', 'name' => 'M1', 'unit_of_measure' => 'un', 'current_stock' => 1, 'min_stock' => 1, 'unit_cost' => 10, 'supplier_id' => $fornecedorTop->id]);
        Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-SUP2', 'name' => 'M2', 'unit_of_measure' => 'un', 'current_stock' => 1, 'min_stock' => 1, 'unit_cost' => 10, 'supplier_id' => $fornecedorTop->id]);
        Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-SUP3', 'name' => 'M3', 'unit_of_measure' => 'un', 'current_stock' => 1, 'min_stock' => 1, 'unit_cost' => 10, 'supplier_id' => $fornecedorBaixo->id]);

        $this->actingAs($admin);

        $data = $this->chartData(new TopSuppliersByMaterialsChartWidget);

        $this->assertSame('Fornecedor Top', $data['labels'][0]);
        $this->assertSame(2, $data['datasets'][0]['data'][0]);
    }
}
