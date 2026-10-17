<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Central\Resources\TenantResource;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pacotes;
use App\Support\Precos;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 8E: valor cheio × recorrente (40%), implantação opcional, acesso por período e preços públicos. */
class PrecosEImplantacaoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.api_key' => 'KEY', 'services.asaas.base_url' => 'https://asaas.test/v3', 'services.asaas.webhook_token' => 'tok']);
        Http::fake(['asaas.test/*' => Http::response(['id' => 'cus_1', 'data' => []], 200)]);
        Cache::flush();
    }

    private function vitrine(string $codigo = 'manutencao', array $extra = []): Plan
    {
        $plano = Pacotes::planoDeVitrine($codigo);
        $plano->update($extra + ['base_price' => 1000, 'price' => 1000, 'is_active' => true, 'implementation_fee' => 900, 'implementation_installments' => 2, 'implementation_billing_mode' => 'separada']);

        return $plano->fresh();
    }

    private function payload(Plan $plano, array $extra = []): array
    {
        return $extra + [
            'plan_id' => $plano->id, 'company_name' => 'Empresa '.uniqid(), 'segment' => Client::NICHE_CONSTRUCAO_CIVIL,
            'equipment_types' => ['gerador'], 'cpf_cnpj' => '123.456.789-09', 'cep' => '13480-000', 'logradouro' => 'Rua A', 'numero' => '1',
            'bairro' => 'Centro', 'cidade' => 'Limeira', 'uf' => 'SP', 'admin_name' => 'Admin', 'admin_email' => 'adm-'.uniqid().'@oravel.com.br',
            'telefone' => '(19) 99999-9999', 'admin_password' => 'senha12345', 'terms_accepted' => '1',
        ];
    }

    public function test_precos_math_rounding_and_payment_forms(): void
    {
        $this->assertSame(600.0, Precos::recorrente(1000));
        $this->assertSame(400.0, Precos::economia(1000));
        $this->assertSame(59.99, Precos::recorrente(99.99));   // 59,994 arredonda
        $this->assertSame(0.0, Precos::recorrente(0));
        $this->assertSame(600.0, Precos::paraForma(1000, 'cartao'));
        $this->assertSame(1000.0, Precos::paraForma(1000, 'boleto_pix'));
        $this->assertSame('R$ 1.234,50', Precos::brl(1234.5));

        config(['precos.desconto_recorrente_percentual' => 25]);
        $this->assertSame(750.0, Precos::recorrente(1000), 'o percentual vem só da configuração');
    }

    public function test_migration_adds_columns_and_is_reversible(): void
    {
        $this->assertTrue(Schema::hasColumn('tenant_complementos', 'valor_cheio'));
        $this->assertTrue(Schema::hasColumn('plans', 'implantacao_opcional'));
        $this->assertTrue(Schema::hasColumn('tenants', 'acesso_ate'));
        $this->assertTrue(Pacotes::planoDeVitrine('vendas')->implantacao_opcional, 'vitrine nasce com implantação opcional');

        $m = require base_path('database/migrations/2026_11_27_100000_add_valor_cheio_e_implantacao_opcional.php');
        $m->down();
        $this->assertFalse(Schema::hasColumn('tenants', 'acesso_por_periodo'));
        $m->up();
        $this->assertTrue(Schema::hasColumn('plans', 'implantacao_opcional'));
    }

    public function test_checkout_form_shows_both_prices_and_the_optional_implementation(): void
    {
        $plano = $this->vitrine();

        $this->get('/assinar/manutencao')->assertOk()
            ->assertSee('R$ 600,00/mês', false)->assertSee('R$ 1.000,00/mês', false)->assertSee('40% de desconto')
            ->assertSee('Contratar implantação')->assertSee('Não contratar agora')->assertSee('R$ 900,00', false)
            ->assertSee('30 dias de acesso');

        $this->get('/assinar/manutencao')->assertSee('name="forma_pagamento"', false);
        $plano->update(['implantacao_opcional' => false]);
        $this->get('/assinar/manutencao')->assertDontSee('Não contratar agora');
        $plano->update(['implementation_fee' => 0]);
        $this->get('/assinar/manutencao')->assertDontSee('Contratar implantação');
    }

    public function test_card_gets_the_discount_and_boleto_pix_pays_full_with_period_access(): void
    {
        $plano = $this->vitrine();

        $this->post('/assinar', $this->payload($plano, ['forma_pagamento' => 'cartao', 'implantacao' => 'contratar', 'company_name' => 'Cartao Ltda']))->assertRedirect();
        $this->post('/assinar', $this->payload($plano, ['forma_pagamento' => 'boleto_pix', 'implantacao' => 'contratar', 'company_name' => 'Boleto Ltda']))->assertRedirect();

        $cartao = Tenant::where('name', 'Cartao Ltda')->firstOrFail();
        $boleto = Tenant::where('name', 'Boleto Ltda')->firstOrFail();

        $this->assertSame('cartao', $cartao->payment_method);
        $this->assertEquals(600, $cartao->mrr_value);
        $this->assertFalse($cartao->acesso_por_periodo);
        $this->assertSame('boleto_pix', $boleto->payment_method);
        $this->assertEquals(1000, $boleto->mrr_value);
        $this->assertTrue($boleto->acesso_por_periodo);
        $this->assertTrue($cartao->implantacao_contratada);
    }

    public function test_the_client_may_decline_the_implementation_when_it_is_optional(): void
    {
        $plano = $this->vitrine();

        $this->post('/assinar', $this->payload($plano, ['forma_pagamento' => 'cartao', 'implantacao' => 'nao_contratar', 'company_name' => 'Sem Implantacao']))->assertRedirect();

        $t = Tenant::where('name', 'Sem Implantacao')->firstOrFail();
        $this->assertFalse($t->implantacao_contratada);
        $this->assertSame(0.0, $t->implementationAmount(), '0 (não nulo): não cai no valor do contrato');
        $this->assertSame('Dispensada pelo cliente', $t->implementationSummary()['label']);

        $obrigatoria = $this->vitrine('servicos', ['implantacao_opcional' => false]);
        $this->post('/assinar', $this->payload($obrigatoria, ['forma_pagamento' => 'cartao', 'implantacao' => 'nao_contratar', 'company_name' => 'Obrigatoria']))->assertRedirect();
        $o = Tenant::where('name', 'Obrigatoria')->firstOrFail();
        $this->assertTrue($o->implantacao_contratada, 'implantação obrigatória não pode ser dispensada');
        $this->assertSame(900.0, $o->implementationAmount());
    }

    public function test_showcase_checkout_requires_the_payment_choice_and_the_implementation_choice(): void
    {
        $plano = $this->vitrine();

        $this->post('/assinar', $this->payload($plano, ['company_name' => 'Sem Escolha']))->assertSessionHasErrors(['forma_pagamento']);
        $this->post('/assinar', $this->payload($plano, ['forma_pagamento' => 'cartao', 'company_name' => 'Sem Escolha']))->assertSessionHasErrors(['implantacao']);
        $this->assertNull(Tenant::where('name', 'Sem Escolha')->first());
    }

    // ---------------------------------------------------------------- acesso por período (valor mensal)

    private function webhook(Tenant $t, string $pagamento, string $evento = 'PAYMENT_CONFIRMED')
    {
        return $this->postJson('/api/webhooks/asaas', ['event' => $evento, 'payment' => ['id' => $pagamento, 'customer' => $t->asaas_customer_id, 'subscription' => $t->asaas_subscription_id, 'status' => 'CONFIRMED', 'value' => 1000]], ['asaas-access-token' => 'tok']);
    }

    private function tenantMensal(): Tenant
    {
        return Tenant::create(['name' => 'Mensal '.uniqid(), 'slug' => 'm-'.uniqid(), 'plan_id' => $this->vitrine()->id, 'status' => 'active', 'mrr_value' => 1000,
            'asaas_customer_id' => 'cus_'.uniqid(), 'asaas_subscription_id' => 'sub_'.uniqid(), 'payment_method' => 'boleto_pix', 'acesso_por_periodo' => true]);
    }

    public function test_each_payment_releases_30_days_once_and_extends_from_the_end_when_still_valid(): void
    {
        $t = $this->tenantMensal();

        $this->webhook($t, 'pay_1')->assertOk();
        $this->assertSame(now()->addDays(30)->toDateString(), $t->fresh()->acesso_ate->toDateString());

        $this->webhook($t, 'pay_1', 'PAYMENT_RECEIVED');   // mesmo pagamento (confirmado e recebido): não estende de novo
        $this->assertSame(now()->addDays(30)->toDateString(), $t->fresh()->acesso_ate->toDateString());

        $this->webhook($t, 'pay_2');
        $this->assertSame(now()->addDays(60)->toDateString(), $t->fresh()->acesso_ate->toDateString(), 'novo pagamento antes de vencer soma 30 dias ao fim do período');
    }

    public function test_access_blocks_by_itself_after_the_period_and_returns_when_paid(): void
    {
        $t = $this->tenantMensal();
        $t->forceFill(['acesso_ate' => now()->subDay()->toDateString(), 'asaas_payment_status' => 'em_dia'])->save();

        $this->assertTrue($t->fresh()->isAccessBlockedForNonPayment(), 'sem tolerância: venceu, bloqueia');

        $admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@x.com', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $this->actingAs($admin)->get('/admin/conta-bloqueada')->assertOk()->assertSee('30 dias de acesso');

        $this->webhook($t, 'pay_novo');
        $this->assertFalse($t->fresh()->isAccessBlockedForNonPayment(), 'pagou: libera sozinho, 30 dias a partir de hoje');
        $this->assertSame(now()->addDays(30)->toDateString(), $t->fresh()->acesso_ate->toDateString());
    }

    public function test_card_clients_and_old_clients_never_use_the_period_rule(): void
    {
        $cartao = Tenant::create(['name' => 'Cartao '.uniqid(), 'slug' => 'c-'.uniqid(), 'plan_id' => $this->vitrine()->id, 'status' => 'active', 'mrr_value' => 600,
            'asaas_customer_id' => 'cus_c', 'acesso_ate' => now()->subMonth()->toDateString()]);

        $this->assertFalse($cartao->isAccessBlockedForNonPayment(), 'sem acesso_por_periodo a data não bloqueia');
        $this->assertFalse($cartao->estenderAcessoPorPagamento('p1'));
    }

    // ---------------------------------------------------------------- API pública de preços

    public function test_public_prices_api_exposes_only_active_priced_showcases_with_cors(): void
    {
        $this->vitrine('manutencao');
        $this->vitrine('vendas', ['implementation_fee' => 0]);
        Pacotes::planoDeVitrine('estoque');                       // sem preço, inativa
        Pacotes::planoDeVitrine('servicos')->update(['base_price' => 500, 'price' => 500, 'is_active' => false]);
        Plan::create(['name' => 'Cliente X', 'price' => 99, 'base_price' => 99, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [], 'pacote' => 'servicos']);

        $r = $this->getJson('/api/public/planos', ['Origin' => 'https://oravel.com.br'])->assertOk();

        $codigos = collect($r->json('data'))->pluck('codigo')->sort()->values()->all();
        $this->assertSame(['manutencao', 'vendas'], $codigos);

        $m = collect($r->json('data'))->firstWhere('codigo', 'manutencao');
        $this->assertEquals(1000, $m['valor_cheio']);
        $this->assertEquals(600, $m['valor_recorrente']);
        $this->assertEquals(40, $m['desconto_percentual']);
        $this->assertEquals(['opcional' => true, 'valor' => 900, 'parcelas_max' => 2, 'modo' => 'separada'], $m['implantacao']);
        $this->assertStringEndsWith('/assinar/manutencao', $m['checkout_url']);
        $this->assertNull(collect($r->json('data'))->firstWhere('codigo', 'vendas')['implantacao']);
        $r->assertHeader('Access-Control-Allow-Origin');   // o site (outro domínio) consegue ler
        $this->assertStringContainsString('max-age=300', $r->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('tenant', json_encode($r->json()), 'nada de dados de clientes');
    }

    public function test_public_prices_cache_is_cleared_when_a_showcase_changes(): void
    {
        $plano = $this->vitrine();
        $this->assertEquals(1000, $this->getJson('/api/public/planos')->json('data.0.valor_cheio'));

        $plano->update(['base_price' => 2000, 'price' => 2000]);

        $this->assertEquals(2000, $this->getJson('/api/public/planos')->json('data.0.valor_cheio'));
    }

    // ---------------------------------------------------------------- Central

    private function superAdmin(): User
    {
        $u = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'is_approved' => true]);
        config(['oravel.super_admins' => [$u->email]]);
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        return $u;
    }

    public function test_central_shows_the_optional_toggle_only_in_the_showcase_and_hires_the_implementation_later(): void
    {
        $this->superAdmin();
        $plano = $this->vitrine();
        $comum = Plan::create(['name' => 'Cliente', 'price' => 10, 'base_price' => 10, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        Livewire::test(ContratoResource\Pages\EditContrato::class, ['record' => $plano->getKey()])->assertFormFieldIsVisible('implantacao_opcional');
        Livewire::test(ContratoResource\Pages\EditContrato::class, ['record' => $comum->getKey()])->assertFormFieldIsHidden('implantacao_opcional');

        $cliente = Tenant::create(['name' => 'Dispensou', 'slug' => 'd-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active', 'mrr_value' => 600, 'implementation_fee' => 0, 'implantacao_contratada' => false, 'asaas_customer_id' => 'cus_9']);

        Livewire::test(TenantResource\Pages\ListTenants::class)
            ->assertTableActionVisible('contratarImplantacao', $cliente)
            ->filterTable('implantacao', 'dispensada')->assertCanSeeTableRecords([$cliente])
            ->callTableAction('contratarImplantacao', $cliente);

        $cliente->refresh();
        $this->assertTrue($cliente->implantacao_contratada);
        $this->assertSame(900.0, $cliente->implementationAmount(), 'volta a valer o valor do contrato');
        $this->assertSame('separada', $cliente->implementationBillingMode());
    }
}
