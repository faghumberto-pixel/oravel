<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\SignatureService;
use App\Support\ContractModules;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regressao (2026-10-01): a lista "Módulos incluídos" do Contrato de Assinatura
 * saía como "1 1 1 1..." porque Plan.features é um mapa {chave: true|false} e a
 * view lia os VALORES em vez das chaves (e listava também os desligados).
 */
class ContractModulesListTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantWithFeatures(array $features): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Modulos '.uniqid(), 'price' => 600, 'base_price' => 600, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);

        return Tenant::create([
            'name' => 'Cliente Modulos', 'slug' => 'cliente-modulos-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial',
        ]);
    }

    public function test_only_enabled_modules_are_listed_grouped_by_menu(): void
    {
        $tenant = $this->tenantWithFeatures([
            'tabela_assets' => true, 'tabela_aggregate_items' => true, 'tabela_clients' => false,
        ]);

        $grouped = ContractModules::grouped($tenant->plan);

        $this->assertSame(['Ativos / Frota'], $grouped['Ativos']);
        $this->assertSame(['Itens Agregados'], $grouped['Itens Agregados']);
        $this->assertCount(2, $grouped);
        $this->assertSame([], ContractModules::grouped($this->tenantWithFeatures([])->plan));
    }

    public function test_legacy_list_format_still_works(): void
    {
        $grouped = ContractModules::grouped($this->tenantWithFeatures(['tabela_assets'])->plan);

        $this->assertSame(['Ativos / Frota'], $grouped['Ativos']);
    }

    public function test_signature_page_shows_module_names_not_ones(): void
    {
        $tenant = $this->tenantWithFeatures(['tabela_assets' => true, 'tabela_clients' => false]);

        $link = app(SignatureService::class)->generateSignatureLink($tenant, ['name' => 'Fulano', 'email' => 'f@x.com']);
        $response = $this->get($link)->assertOk();

        $response->assertSee('Ativos / Frota');
        $response->assertDontSee('<li>1</li>', false);
        $response->assertSee('Módulos incluídos');
    }

    public function test_contract_states_the_erp_is_modular_with_individual_prices(): void
    {
        $tenant = $this->tenantWithFeatures(['tabela_assets' => true]);

        $html = view('partials.subscription-agreement-clauses', ['contract' => $tenant])->render();

        $this->assertStringContainsString('Natureza modular do ERP', $html);
        $this->assertStringContainsString('ERP completo', $html);
        $this->assertStringContainsString('ecossistema', $html);
        $this->assertStringContainsString('não representa a contratação nem o uso integral do ERP', $html);
        $this->assertStringContainsString('Cada módulo e cada funcionalidade possui preço individual', $html);
        $this->assertStringContainsString('precificados individualmente', $html);
    }
}
