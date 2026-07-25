<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ClientResource\Widgets\ClientActiveContractGaugeWidget;
use App\Filament\Resources\CrmLeadResource;
use App\Filament\Resources\CrmLeadResource\Widgets\CrmLeadsCreatedTrendWidget;
use App\Filament\Resources\MaterialResource;
use App\Filament\Resources\MaterialResource\Widgets\MaterialStockHealthGaugeWidget;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\SupplierResource\Widgets\SupplierComplianceGaugeWidget;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CrmLead;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
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

    public function test_assets_index_shows_status_chart(): void
    {
        [, $admin] = $this->makeTenantAdmin(['tabela_assets']);
        $this->actingAs($admin);

        $html = $this->get(AssetResource::getUrl('index'))->assertOk()->getContent();

        $this->assertStringContainsString('Ativos por Status', $html);
    }

    public function test_leads_index_shows_created_trend_chart(): void
    {
        [, $admin] = $this->makeTenantAdmin(['tabela_crm_leads']);
        $this->actingAs($admin);

        $this->get(CrmLeadResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Leads Criados por Mês');
    }

    public function test_clients_index_shows_active_contract_gauge(): void
    {
        [, $admin] = $this->makeTenantAdmin(['tabela_clients']);
        $this->actingAs($admin);

        $this->get(ClientResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Taxa de Clientes com Contrato Ativo');
    }

    public function test_materials_index_shows_stock_health_gauge(): void
    {
        [, $admin] = $this->makeTenantAdmin(['tabela_materials']);
        $this->actingAs($admin);

        $this->get(MaterialResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Taxa de Estoque Saudável');
    }

    public function test_suppliers_index_shows_compliance_gauge(): void
    {
        [, $admin] = $this->makeTenantAdmin(['tabela_suppliers']);
        $this->actingAs($admin);

        $this->get(SupplierResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Taxa de Compliance Completo');
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
}
