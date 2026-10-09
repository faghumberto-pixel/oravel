<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\AggregateItem;
use App\Models\AggregateItemEntry;
use App\Models\AggregateItemExit;
use App\Models\AggregateItemType;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\SpecializedService;
use App\Models\Tenant;
use App\Services\ContractCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Custo real do contrato (mão de obra + saídas de acessórios/insumos) contra o faturado. */
class ContractCostTest extends TestCase
{
    use RefreshDatabase;

    private function setupTenant(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Custo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_contracts'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Custo '.uniqid(), 'slug' => 'tenant-custo-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Custo']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Custo', 'status' => Asset::STATUS_LOCADO]);
        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now()->subMonth(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 5000, 'status' => 'Ativo',
        ]);

        return [$tenant, $client, $asset, $contract];
    }

    private function tipo(Tenant $tenant, string $categoria, string $nome): AggregateItemType
    {
        return AggregateItemType::create(['tenant_id' => $tenant->id, 'category' => $categoria, 'name' => $nome, 'unit_of_measure' => 'un']);
    }

    public function test_summary_sums_labor_accessories_and_supplies_against_billed(): void
    {
        [$tenant, $client, $asset, $contract] = $this->setupTenant();

        SpecializedService::create([
            'tenant_id' => $tenant->id, 'service_type' => 'operador', 'title' => 'Operador',
            'contract_id' => $contract->id, 'cost' => 3000, 'status' => 'em_andamento',
        ]);
        SpecializedService::create([
            'tenant_id' => $tenant->id, 'service_type' => 'engenharia', 'title' => 'Cancelado',
            'contract_id' => $contract->id, 'cost' => 9999, 'status' => 'cancelado',
        ]);

        $insumo = $this->tipo($tenant, AggregateItemType::CATEGORY_INSUMO, 'Óleo');
        AggregateItemEntry::create([
            'tenant_id' => $tenant->id, 'aggregate_item_type_id' => $insumo->id,
            'entry_date' => now(), 'unit_price' => 40, 'quantity' => 100, 'total' => 4000,
        ]);
        AggregateItemExit::create([
            'tenant_id' => $tenant->id, 'aggregate_item_type_id' => $insumo->id,
            'asset_id' => $asset->id, 'exit_date' => now(), 'quantity' => 5, 'reason' => 'locacao',
        ]);

        $acessorio = $this->tipo($tenant, AggregateItemType::CATEGORY_ACESSORIO, 'Bandeja');
        $unidade = AggregateItem::create([
            'tenant_id' => $tenant->id, 'aggregate_item_type_id' => $acessorio->id, 'code' => 'BAN-1', 'purchase_value' => 700,
        ]);
        AggregateItemExit::create([
            'tenant_id' => $tenant->id, 'aggregate_item_type_id' => $acessorio->id, 'aggregate_item_id' => $unidade->id,
            'asset_id' => $asset->id, 'exit_date' => now(), 'quantity' => 1, 'reason' => 'locacao',
        ]);

        AccountReceivable::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'contract_id' => $contract->id,
            'description' => 'Fatura', 'amount' => 5000, 'due_date' => now()->addDays(10),
        ]);

        $r = app(ContractCostService::class)->summary($contract);

        $this->assertEqualsWithDelta(3000.0, $r['lines']['mao_de_obra'], 0.01);
        $this->assertEqualsWithDelta(200.0, $r['lines']['insumo'], 0.01);
        $this->assertEqualsWithDelta(700.0, $r['lines']['acessorio'], 0.01);
        $this->assertEqualsWithDelta(3900.0, $r['cost'], 0.01);
        $this->assertEqualsWithDelta(5000.0, $r['billed'], 0.01);
        $this->assertEqualsWithDelta(1100.0, $r['margin'], 0.01);
        $this->assertSame(22.0, $r['margin_pct']);
    }

    public function test_exit_links_to_assets_active_contract_and_freezes_unit_cost(): void
    {
        [$tenant, , $asset, $contract] = $this->setupTenant();
        $insumo = $this->tipo($tenant, AggregateItemType::CATEGORY_INSUMO, 'Graxa');
        AggregateItemEntry::create([
            'tenant_id' => $tenant->id, 'aggregate_item_type_id' => $insumo->id,
            'entry_date' => now(), 'unit_price' => 10, 'quantity' => 10, 'total' => 100,
        ]);

        $saida = AggregateItemExit::create([
            'tenant_id' => $tenant->id, 'aggregate_item_type_id' => $insumo->id,
            'asset_id' => $asset->id, 'exit_date' => now(), 'quantity' => 2, 'reason' => 'locacao',
        ]);

        $this->assertSame($contract->id, $saida->contract_id);
        $this->assertEqualsWithDelta(10.0, (float) $saida->unit_cost, 0.01);

        // Preço de compra sobe depois: o custo da saída já registrada não muda.
        AggregateItemEntry::create([
            'tenant_id' => $tenant->id, 'aggregate_item_type_id' => $insumo->id,
            'entry_date' => now(), 'unit_price' => 90, 'quantity' => 10, 'total' => 900,
        ]);
        $this->assertEqualsWithDelta(10.0, (float) $saida->fresh()->unit_cost, 0.01);
    }

    public function test_returned_ok_items_do_not_count_and_service_contract_exit_without_asset_works(): void
    {
        [$tenant, , , $contract] = $this->setupTenant();
        $insumo = $this->tipo($tenant, AggregateItemType::CATEGORY_INSUMO, 'Filtro');

        $volta = AggregateItemExit::create([
            'tenant_id' => $tenant->id, 'aggregate_item_type_id' => $insumo->id, 'contract_id' => $contract->id,
            'unit_cost' => 50, 'exit_date' => now(), 'quantity' => 2, 'reason' => 'locacao',
        ]);
        AggregateItemExit::create([
            'tenant_id' => $tenant->id, 'aggregate_item_type_id' => $insumo->id, 'contract_id' => $contract->id,
            'unit_cost' => 50, 'exit_date' => now(), 'quantity' => 1, 'reason' => 'locacao',
        ]);
        $this->assertEqualsWithDelta(150.0, app(ContractCostService::class)->summary($contract)['lines']['insumo'], 0.01);

        $volta->registerReturn(AggregateItemExit::CONDITION_OK);
        $this->assertEqualsWithDelta(50.0, app(ContractCostService::class)->summary($contract)['lines']['insumo'], 0.01);
    }
}
