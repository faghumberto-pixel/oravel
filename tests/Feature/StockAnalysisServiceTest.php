<?php

namespace Tests\Feature;

use App\Filament\Pages\AnaliseEstoque;
use App\Models\AIAnalysis;
use App\Models\Material;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class StockAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Estoque '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_materials', 'ia_diagnostico_avarias'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Estoque '.uniqid(), 'slug' => 'tenant-estoque-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function fakeClaudeJsonResponse(array $payload): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => json_encode($payload)],
                ],
            ], 200),
        ]);
    }

    public function test_marks_analysis_as_failed_without_calling_api_when_nothing_is_critical_or_dead(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $material = Material::create([
            'tenant_id' => $tenant->id, 'sku' => 'SKU-'.uniqid(), 'name' => 'Filtro de óleo',
            'unit_cost' => 50, 'min_stock' => 5, 'max_stock' => 50, 'current_stock' => 20,
        ]);

        StockMovement::create([
            'tenant_id' => $tenant->id, 'material_id' => $material->id,
            'type' => StockMovement::TYPE_SAIDA_CONSUMO, 'quantity' => 3, 'balance_after' => 20,
            'created_at' => now()->subDays(5),
        ]);

        Http::fake();

        $analysis = app(StockAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_FALHOU, $analysis->status);
        $this->assertStringContainsString('Nenhum material', $analysis->error);
        Http::assertNothingSent();
    }

    public function test_flags_critical_material_with_estimated_days_and_dead_stock_material(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $critico = Material::create([
            'tenant_id' => $tenant->id, 'sku' => 'CRIT-'.uniqid(), 'name' => 'Correia dentada',
            'unit_cost' => 100, 'min_stock' => 10, 'max_stock' => 100, 'current_stock' => 5,
        ]);

        // Consumo total de 30 unidades nos ultimos 90 dias -> 10/mes ->
        // ~0.333/dia -> 5 / 0.333 = 15 dias estimados. created_at nao e'
        // fillable em StockMovement (ledger), por isso forceFill+save
        // depois de criar, em vez de passar no create().
        StockMovement::create([
            'tenant_id' => $tenant->id, 'material_id' => $critico->id,
            'type' => StockMovement::TYPE_SAIDA_CONSUMO, 'quantity' => 30, 'balance_after' => 5,
        ])->forceFill(['created_at' => now()->subDays(10)])->save();

        $parado = Material::create([
            'tenant_id' => $tenant->id, 'sku' => 'PARADO-'.uniqid(), 'name' => 'Peça obsoleta',
            'unit_cost' => 200, 'min_stock' => 0, 'max_stock' => 50, 'current_stock' => 20,
        ]);

        // Movimentacao antiga (fora da janela de 90 dias) -- ainda conta
        // como "parado" porque nao ha consumo RECENTE.
        StockMovement::create([
            'tenant_id' => $tenant->id, 'material_id' => $parado->id,
            'type' => StockMovement::TYPE_SAIDA_CONSUMO, 'quantity' => 5, 'balance_after' => 20,
        ])->forceFill(['created_at' => now()->subDays(200)])->save();

        $this->fakeClaudeJsonResponse([
            'resumo_geral' => 'Um item crítico e um parado.',
            'prioridades_compra' => ['Comprar correia dentada urgentemente.'],
            'recomendacoes_estoque_parado' => ['Avaliar devolução da peça obsoleta.'],
        ]);

        $analysis = app(StockAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_CONCLUIDA, $analysis->status);

        $criticos = $analysis->response['materiais_criticos'];
        $this->assertCount(1, $criticos);
        $this->assertSame('Correia dentada', $criticos[0]['nome']);
        $this->assertEquals(15.0, $criticos[0]['dias_estimados_para_esgotar']);
        $this->assertFalse($criticos[0]['tem_pedido_compra_aberto']);

        $parados = $analysis->response['materiais_parados'];
        $this->assertCount(1, $parados);
        $this->assertSame('Peça obsoleta', $parados[0]['nome']);
        $this->assertEquals(4000.0, $parados[0]['valor_parado']);
    }

    public function test_material_with_open_purchase_order_is_flagged_as_already_covered(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $supplier = Supplier::create(['tenant_id' => $tenant->id, 'name' => 'Fornecedor X']);

        $material = Material::create([
            'tenant_id' => $tenant->id, 'sku' => 'CRIT2-'.uniqid(), 'name' => 'Filtro de ar',
            'unit_cost' => 30, 'min_stock' => 10, 'max_stock' => 50, 'current_stock' => 2,
        ]);

        $order = PurchaseOrder::create([
            'tenant_id' => $tenant->id, 'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_ABERTA, 'total_value' => 300,
            'created_by_user_id' => $admin->id,
        ]);

        PurchaseOrderItem::create([
            'tenant_id' => $tenant->id, 'purchase_order_id' => $order->id, 'material_id' => $material->id,
            'quantity' => 20, 'unit_price' => 15,
        ]);

        $this->fakeClaudeJsonResponse([
            'resumo_geral' => 'Item crítico já coberto por pedido em aberto.',
            'prioridades_compra' => [],
            'recomendacoes_estoque_parado' => [],
        ]);

        $analysis = app(StockAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $this->assertTrue($analysis->response['materiais_criticos'][0]['tem_pedido_compra_aberto']);
    }

    public function test_analise_estoque_page_action_creates_ai_analysis_of_type_estoque(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Material::create([
            'tenant_id' => $tenant->id, 'sku' => 'PAGE-'.uniqid(), 'name' => 'Vela de ignição',
            'unit_cost' => 10, 'min_stock' => 5, 'max_stock' => 20, 'current_stock' => 1,
        ]);

        $this->fakeClaudeJsonResponse([
            'resumo_geral' => 'ok',
            'prioridades_compra' => [],
            'recomendacoes_estoque_parado' => [],
        ]);

        Livewire::test(AnaliseEstoque::class)
            ->callAction('analisar');

        $this->assertDatabaseHas('ai_analyses', [
            'tenant_id' => $tenant->id,
            'type' => AIAnalysis::TYPE_ESTOQUE,
            'status' => AIAnalysis::STATUS_CONCLUIDA,
        ]);
    }
}
