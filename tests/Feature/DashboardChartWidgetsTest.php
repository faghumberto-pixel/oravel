<?php

namespace Tests\Feature;

use App\Filament\Pages\PainelGestao;
use App\Filament\Widgets\FleetAvailabilityGaugeWidget;
use App\Filament\Widgets\MaintenanceCostChart;
use App\Filament\Widgets\MaintenanceOrdersOpenVsClosedAreaWidget;
use App\Filament\Widgets\TopClientsByRentals;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
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

    private function makeTenantAdmin(?string $segment = null): array
    {
        $plan = Plan::create([
            'name' => 'Plano Dash Charts '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders', 'modulo_dashboard'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Dash Charts '.uniqid(), 'slug' => 'tenant-dash-charts-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'segment' => $segment,
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

    /**
     * Pedido explícito do usuário: Ativos por Status, Manutenções por
     * Status e Custo de Manutenção por Mês (nessa ordem, já é a ordem real
     * do array default de SegmentDashboardWidgets) na mesma linha -- só
     * pro segmento genérico (sem Eventos/Construção Civil/
     * Industrial-Hospitalar), pra não mexer no layout dos outros 3.
     */
    public function test_default_segment_dashboard_uses_3_column_grid(): void
    {
        [, $admin] = $this->makeTenantAdmin(segment: null);
        $this->actingAs($admin);

        $html = $this->get(PainelGestao::getUrl())->assertOk()->getContent();

        $this->assertStringContainsString('lg:grid-cols-3', $html);
    }

    public function test_other_segments_keep_2_column_grid(): void
    {
        [, $admin] = $this->makeTenantAdmin(segment: Client::NICHE_EVENTOS);
        $this->actingAs($admin);

        $html = $this->get(PainelGestao::getUrl())->assertOk()->getContent();

        $this->assertStringNotContainsString('lg:grid-cols-3', $html);
        $this->assertStringContainsString('lg:grid-cols-2', $html);
    }

    /**
     * Segundo pedido do usuário: Top Clientes / Taxa de Disponibilidade
     * da Frota / O.S. Abertas vs. Concluídas também na mesma linha -- são
     * os itens 4-6 do array default, então com o grid já em 3 colunas
     * (ver teste acima) já caem sozinhos na 2ª linha, sem mudança de
     * código necessária. Este teste prova isso e protege contra alguém
     * reordenar o array e quebrar o agrupamento sem perceber.
     *
     * "Top 3" (não "Top 5") no segmento genérico -- terceiro pedido do
     * usuário, reduzir a lista pra caber melhor na coluna de 1/3.
     */
    public function test_second_row_groups_top_clients_gauge_and_area_together(): void
    {
        [, $admin] = $this->makeTenantAdmin(segment: null);
        $this->actingAs($admin);

        $html = $this->get(PainelGestao::getUrl())->assertOk()->getContent();

        $posCost = strpos($html, 'Custo de Manutenção por Mês');
        $posTopClients = strpos($html, 'Top 3 Clientes com Mais Locações');
        $posGauge = strpos($html, 'Taxa de Disponibilidade da Frota');
        $posArea = strpos($html, 'O.S. Abertas vs. Concluídas por Mês');

        $this->assertNotFalse($posCost);
        $this->assertNotFalse($posTopClients);
        $this->assertNotFalse($posGauge);
        $this->assertNotFalse($posArea);

        // Ordem exata dos 3 últimos itens do gridWidgets, todos depois do
        // fim da 1ª linha (Custo de Manutenção).
        $this->assertTrue($posCost < $posTopClients);
        $this->assertTrue($posTopClients < $posGauge);
        $this->assertTrue($posGauge < $posArea);
    }

    /**
     * O bug real que o usuário reportou: DOM order sozinho (teste acima)
     * não pega isso -- TopClientsByRentals tinha columnSpan=['md'=>2],
     * que o wrapper interno de todo widget Filament
     * (vendor/filament/widgets/resources/views/components/widget.blade.php,
     * via <x-filament::grid.column>) transforma numa classe/estilo
     * "span 2 / span 2" REAL, mesmo dentro deste grid CSS hand-rolled.
     * Num container de 3 colunas, isso empurrava o próximo item pra uma
     * linha nova sozinho -- o "3, depois 2, depois 1" que o usuário
     * descreveu. No segmento genérico não pode sobrar nenhum "span 2".
     */
    public function test_default_segment_has_no_wide_grid_items(): void
    {
        [, $admin] = $this->makeTenantAdmin(segment: null);
        $this->actingAs($admin);

        $html = $this->get(PainelGestao::getUrl())->assertOk()->getContent();

        $this->assertStringNotContainsString('span 2 / span 2', $html);
    }

    /**
     * O segmento Eventos não foi pedido pra mudar -- TopClientsByRentals
     * continua span 2 lá (ocupa a linha inteira sozinho, comportamento
     * de propósito), só o segmento genérico foi ajustado.
     */
    public function test_eventos_segment_keeps_wide_top_clients_widget(): void
    {
        [, $admin] = $this->makeTenantAdmin(segment: Client::NICHE_EVENTOS);
        $this->actingAs($admin);

        $html = $this->get(PainelGestao::getUrl())->assertOk()->getContent();

        $this->assertStringContainsString('span 2 / span 2', $html);
    }

    /**
     * Terceiro pedido do usuário: Top 3 (não Top 5) no segmento genérico,
     * pra caber melhor na coluna de 1/3. Eventos continua Top 5 -- não foi
     * pedido pra mudar, e lá o widget ocupa a linha inteira.
     */
    public function test_generic_segment_shows_only_top_3_clients(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(segment: null);

        foreach (range(1, 5) as $i) {
            $client = Client::create(['tenant_id' => $tenant->id, 'name' => "Cliente {$i}"]);
            $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => "Ativo {$i}", 'patrimonio' => "PAT-TC-{$i}", 'status' => 'disponivel']);
            Contract::create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
                'contract_number' => "CT-{$i}", 'start_date' => now(), 'price' => 100,
            ]);
        }

        $this->actingAs($admin);

        $component = Livewire::test(TopClientsByRentals::class);

        $this->assertCount(3, $component->instance()->getTableRecords());
    }

    public function test_eventos_segment_still_shows_top_5_clients(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(segment: Client::NICHE_EVENTOS);

        foreach (range(1, 5) as $i) {
            $client = Client::create(['tenant_id' => $tenant->id, 'name' => "Cliente {$i}"]);
            $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => "Ativo {$i}", 'patrimonio' => "PAT-TC-{$i}", 'status' => 'disponivel']);
            Contract::create([
                'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
                'contract_number' => "CT-{$i}", 'start_date' => now(), 'price' => 100,
            ]);
        }

        $this->actingAs($admin);

        $component = Livewire::test(TopClientsByRentals::class);

        $this->assertCount(5, $component->instance()->getTableRecords());
    }
}
