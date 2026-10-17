<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\TaxaConfiguracaoResource;
use App\Filament\Central\Resources\TenantResource;
use App\Filament\Central\Resources\TenantResource\RelationManagers\TaxasRelationManager;
use App\Models\Client;
use App\Models\DocumentSignature;
use App\Models\Plan;
use App\Models\TaxaConfiguracao;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\TenantTaxa;
use App\Models\User;
use App\Services\ComplementoException;
use App\Services\TaxasService;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 8F: taxas de configuração opcionais, de pagamento único. */
class TaxasConfiguracaoTest extends TestCase
{
    use DatabaseTransactions;

    private int $pagamentos = 0;

    private bool $asaasFalhando = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.api_key' => 'KEY', 'services.asaas.base_url' => 'https://asaas.test/v3']);
        $this->pagamentos = 0;
        $this->asaasFalhando = false;
        Http::fake(['asaas.test/*' => function ($r) {
            $url = $r->url();

            return match (true) {
                $this->asaasFalhando => Http::response(['errors' => []], 500),
                $r->method() === 'DELETE' => Http::response(['deleted' => true], 200),
                str_contains($url, '/payments') => Http::response(['id' => 'pay_taxas_'.(++$this->pagamentos), 'invoiceUrl' => 'https://pague/taxas', 'status' => 'PENDING'], 200),
                str_contains($url, '/checkouts') => Http::response(['id' => 'che_1', 'link' => 'https://asaas.test/checkout/che_1'], 200),
                str_contains($url, '/customers') => Http::response(['id' => 'cus_1', 'data' => []], 200),
                default => Http::response([], 200),
            };
        }]);
        Cache::flush();
    }

    private function tenant(array $extra = []): Tenant
    {
        $plano = Plan::create(['name' => 'Plano '.uniqid(), 'price' => 500, 'base_price' => 500, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        return Tenant::create($extra + ['name' => 'Empresa '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active', 'mrr_value' => 500, 'asaas_customer_id' => 'cus_1']);
    }

    private function servico(): TaxasService
    {
        return app(TaxasService::class);
    }

    private function superAdmin(): User
    {
        $u = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'is_approved' => true]);
        config(['oravel.super_admins' => [$u->email]]);
        $this->actingAs($u);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        return $u;
    }

    public function test_catalog_is_seeded_with_the_agreed_values_and_migration_has_no_price(): void
    {
        $this->assertTrue(Schema::hasTable('tenant_taxas'));
        $v = TaxaConfiguracao::pluck('valor', 'codigo');

        $this->assertEquals(98, $v['nfe_nfse']);
        $this->assertEquals(70, $v['email']);
        $this->assertEquals(89, $v['whatsapp']);
        $this->assertEquals(115, $v['cobranca_automatica']);
        $this->assertNull($v['migracao_dados']);
        $this->assertSame(['nfe_nfse', 'email', 'whatsapp', 'cobranca_automatica'], TaxaConfiguracao::disponiveis()->pluck('codigo')->all(), 'migração sem valor não aparece');

        $m = require base_path('database/migrations/2026_11_28_100000_create_taxas_configuracao.php');
        $m->down();
        $this->assertFalse(Schema::hasTable('taxas_configuracao'));
        $m->up();
        $this->assertSame(5, TaxaConfiguracao::count());
    }

    public function test_approval_freezes_name_and_value_ignores_invalid_codes_and_duplicates(): void
    {
        $t = $this->tenant();

        $criadas = $this->servico()->aprovar($t, ['email', 'whatsapp', 'migracao_dados', 'inexistente', 'email'], 'checkout', '203.0.113.9');

        $this->assertSame(['email', 'whatsapp'], $criadas->pluck('taxa_codigo')->sort()->values()->all());
        $email = TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->where('taxa_codigo', 'email')->firstOrFail();
        $this->assertEquals(70, $email->valor);
        $this->assertSame('203.0.113.9', $email->aprovada_ip);
        $this->assertSame('aprovada', $email->status);

        TaxaConfiguracao::where('codigo', 'email')->update(['valor' => 999]);
        $this->assertEquals(70, $email->fresh()->valor, 'mudar o catálogo não muda o que já foi aprovado');
        $this->assertCount(0, $this->servico()->aprovar($t, ['email'], 'checkout'), 'não duplica taxa vigente');
    }

    public function test_custom_value_needs_a_single_fee_and_a_reason(): void
    {
        $t = $this->tenant();

        foreach ([[['email', 'whatsapp'], 50.0, 'motivo'], [['email'], 50.0, ''], [['email'], 0.0, 'motivo']] as [$cod, $valor, $motivo]) {
            try {
                $this->servico()->aprovar($t, $cod, 'central', null, null, $valor, $motivo);
                $this->fail('deveria recusar');
            } catch (ComplementoException $e) {
                $this->assertStringContainsString('Valor personalizado', $e->getMessage());
            }
        }

        $this->servico()->aprovar($t, ['email'], 'central', null, null, 50.0, 'Acordo comercial');
        $this->assertEquals(50, TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->value('valor'));
    }

    public function test_one_single_charge_with_the_sum_and_it_is_idempotent(): void
    {
        $t = $this->tenant();
        $this->servico()->aprovar($t, ['email', 'whatsapp', 'cobranca_automatica']);

        $link = $this->servico()->cobrar($t);

        $this->assertSame('https://pague/taxas', $link);
        $this->assertSame(['cobrada'], TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->pluck('status')->unique()->all());
        $this->assertSame(1, TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->pluck('asaas_payment_id')->unique()->count());
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payments') && $r['value'] === 274.0 && $r['billingType'] === 'UNDEFINED' && str_starts_with($r['externalReference'], 'taxas:'));

        $this->assertNull($this->servico()->cobrar($t), 'nada novo para cobrar');
        $this->assertSame(1, $this->pagamentos);
    }

    public function test_charge_is_skipped_without_asaas_customer_or_on_failure_and_taxes_stay_approved(): void
    {
        $semCliente = $this->tenant(['asaas_customer_id' => null]);
        $this->servico()->aprovar($semCliente, ['email']);
        $this->assertNull($this->servico()->cobrar($semCliente));

        $this->asaasFalhando = true;
        $t = $this->tenant();
        $this->servico()->aprovar($t, ['email']);

        $this->assertNull($this->servico()->cobrar($t));
        $this->assertSame('aprovada', TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->value('status'));
    }

    private function webhook(string $evento, string $pagamento, ?Tenant $t = null)
    {
        config(['services.asaas.webhook_token' => 'tok']);

        return $this->postJson('/api/webhooks/asaas', ['event' => $evento, 'payment' => ['id' => $pagamento, 'customer' => $t?->asaas_customer_id ?? 'cus_1', 'status' => 'CONFIRMED', 'value' => 100]], ['asaas-access-token' => 'tok']);
    }

    public function test_webhook_marks_the_fees_paid_without_touching_the_subscription_status_and_is_idempotent(): void
    {
        $t = $this->tenant(['asaas_payment_status' => 'em_dia']);
        $this->servico()->aprovar($t, ['email', 'whatsapp']);
        $this->servico()->cobrar($t);
        $pagamento = TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->value('asaas_payment_id');

        $this->webhook('PAYMENT_OVERDUE', $pagamento, $t)->assertOk();
        $this->assertSame('cobrada', TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->value('status'));

        $this->webhook('PAYMENT_CONFIRMED', $pagamento, $t)->assertOk();
        $this->webhook('PAYMENT_RECEIVED', $pagamento, $t)->assertOk();

        $this->assertSame(['paga'], TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->pluck('status')->unique()->all());
        $this->assertSame('em_dia', $t->fresh()->asaas_payment_status, 'taxa não mexe na mensalidade');
        $this->assertSame(1, TenantEvent::withoutGlobalScopes()->where('tenant_id', $t->id)->where('event_type', 'taxa_paga')->count());
    }

    public function test_deleted_payment_reopens_the_fees_to_be_charged_again(): void
    {
        $t = $this->tenant();
        $this->servico()->aprovar($t, ['email']);
        $this->servico()->cobrar($t);
        $pagamento = TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->value('asaas_payment_id');

        $this->webhook('PAYMENT_DELETED', $pagamento, $t)->assertOk();

        $linha = TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame('aprovada', $linha->status);
        $this->assertNull($linha->asaas_payment_id);
        $this->assertNotNull($this->servico()->cobrar($t->refresh()));
    }

    public function test_cancel_only_before_payment_and_cancelling_a_charged_one_reopens_the_others(): void
    {
        $t = $this->tenant();
        $this->servico()->aprovar($t, ['email', 'whatsapp']);
        $this->servico()->cobrar($t);
        $email = TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->where('taxa_codigo', 'email')->firstOrFail();
        $zap = TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->where('taxa_codigo', 'whatsapp')->firstOrFail();

        $this->servico()->cancelar($email);

        $this->assertSame('cancelada', $email->fresh()->status);
        $this->assertSame('aprovada', $zap->fresh()->status, 'a outra taxa da mesma cobrança volta para ser cobrada de novo');
        $this->assertNull($zap->fresh()->asaas_payment_id);
        Http::assertSent(fn ($r) => $r->method() === 'DELETE');

        $zap->update(['status' => 'paga']);
        $this->expectException(ComplementoException::class);
        $this->servico()->cancelar($zap->fresh());
    }

    // ---------------------------------------------------------------- checkout

    private function vitrine(): Plan
    {
        $p = Pacotes::planoDeVitrine('manutencao');
        $p->update(['base_price' => 1000, 'price' => 1000, 'is_active' => true, 'implementation_fee' => 900, 'implementation_installments' => 1, 'implementation_billing_mode' => 'separada']);

        return $p->fresh();
    }

    private function payload(Plan $plano, array $extra = []): array
    {
        return $extra + [
            'plan_id' => $plano->id, 'company_name' => 'Empresa '.uniqid(), 'segment' => Client::NICHE_CONSTRUCAO_CIVIL, 'equipment_types' => ['gerador'],
            'cpf_cnpj' => '123.456.789-09', 'cep' => '13480-000', 'logradouro' => 'Rua A', 'numero' => '1', 'bairro' => 'Centro', 'cidade' => 'Limeira', 'uf' => 'SP',
            'admin_name' => 'Admin', 'admin_email' => 'adm-'.uniqid().'@oravel.com.br', 'telefone' => '(19) 99999-9999', 'admin_password' => 'senha12345',
            'forma_pagamento' => 'cartao', 'implantacao' => 'contratar', 'terms_accepted' => '1',
        ];
    }

    public function test_checkout_lists_the_available_fees_and_hides_the_one_without_a_price(): void
    {
        $this->vitrine();

        $this->get('/assinar/manutencao')->assertOk()
            ->assertSee('Taxas de configuração')->assertSee('Configuração de WhatsApp')->assertSee('R$ 89,00', false)->assertSee('Configuração de NF-e e NFS-e')
            ->assertSee('R$ 115,00', false)->assertSee('pagamento único')->assertSee('Academia Oravel')->assertDontSee('Migração de dados');

        TaxaConfiguracao::where('codigo', 'migracao_dados')->update(['valor' => 250, 'ativa' => true]);
        $this->get('/assinar/manutencao')->assertSee('Migração de dados')->assertSee('R$ 250,00', false);

        TaxaConfiguracao::query()->update(['ativa' => false]);
        $this->get('/assinar/manutencao')->assertDontSee('Taxas de configuração (opcionais');
    }

    public function test_checkout_saves_only_the_chosen_fees_with_the_server_value_and_the_signed_contract_lists_them(): void
    {
        $plano = $this->vitrine();
        $dados = $this->payload($plano, ['company_name' => 'Cliente Das Taxas', 'taxas' => ['whatsapp', 'email', 'codigo_falso'], 'valor' => 1, 'taxas_valor' => ['whatsapp' => 1]]);

        $this->post('/assinar', $dados)->assertRedirect();

        $t = Tenant::where('name', 'Cliente Das Taxas')->firstOrFail();
        $this->assertSame([['email', 70.0], ['whatsapp', 89.0]], TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->orderBy('taxa_codigo')->get()->map(fn ($x) => [$x->taxa_codigo, (float) $x->valor])->all());
        $this->assertSame('checkout', TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->value('origem'));

        $assinatura = DocumentSignature::where('signable_id', $t->id)->firstOrFail();
        $this->get("/assinatura/{$assinatura->token}")->assertOk()->assertSee('ANEXO II')->assertSee('Configuração de WhatsApp')->assertSee('R$ 159,00', false);
    }

    public function test_after_signing_the_fees_and_the_chosen_implementation_are_charged_once(): void
    {
        $plano = $this->vitrine();
        $this->post('/assinar', $this->payload($plano, ['company_name' => 'Cliente Cobrado', 'taxas' => ['whatsapp'], 'forma_pagamento' => 'cartao']))->assertRedirect();
        $t = Tenant::where('name', 'Cliente Cobrado')->firstOrFail();
        $assinatura = DocumentSignature::where('signable_id', $t->id)->firstOrFail();

        $this->postJson("/assinatura/{$assinatura->token}/assinar", [
            'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'signer_name' => 'Admin',
        ])->assertOk();

        $this->get(route('checkout.continue', ['token' => $assinatura->token]))->assertRedirect('https://asaas.test/checkout/che_1');

        $this->assertSame('cobrada', TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->value('status'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payments') && str_starts_with($r['externalReference'] ?? '', 'taxas:') && $r['value'] === 89.0);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payments') && str_starts_with($r['externalReference'] ?? '', 'implantacao:') && $r['value'] === 900.0);

        $this->get(route('checkout.continue', ['token' => $assinatura->token]));
        $this->assertSame(1, TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->pluck('asaas_payment_id')->unique()->count());
    }

    // ---------------------------------------------------------------- API pública e Central

    public function test_public_api_exposes_the_available_fees_and_follows_central_edits(): void
    {
        $r = $this->getJson('/api/public/planos')->assertOk();

        $this->assertEqualsCanonicalizing(['nfe_nfse', 'email', 'whatsapp', 'cobranca_automatica'], collect($r->json('taxas'))->pluck('codigo')->all());
        $this->assertSame('unico', $r->json('taxas.0.pagamento'));
        $this->assertStringNotContainsString('tenant', json_encode($r->json('taxas')));

        TaxaConfiguracao::where('codigo', 'email')->firstOrFail()->update(['valor' => 80]);
        $this->assertEquals(80, collect($this->getJson('/api/public/planos')->json('taxas'))->firstWhere('codigo', 'email')['valor'], 'cache limpo ao editar');
    }

    public function test_central_edits_the_catalog_and_manages_the_client_fees(): void
    {
        $this->superAdmin();
        $edit = TaxaConfiguracao::where('codigo', 'whatsapp')->firstOrFail();

        Livewire::test(TaxaConfiguracaoResource\Pages\ListTaxasConfiguracao::class)->assertSuccessful()->assertCanSeeTableRecords(TaxaConfiguracao::all());
        Livewire::test(TaxaConfiguracaoResource\Pages\EditTaxaConfiguracao::class, ['record' => $edit->getKey()])
            ->fillForm(['valor' => 95, 'ativa' => true])->call('save')->assertHasNoFormErrors();
        $this->assertEquals(95, $edit->fresh()->valor);

        $t = $this->tenant();
        $tela = Livewire::test(TaxasRelationManager::class, ['ownerRecord' => $t, 'pageClass' => TenantResource\Pages\EditTenant::class])->assertSuccessful()
            ->callTableAction('contratar', data: ['codigo' => 'email']);
        $linha = TenantTaxa::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame('central', $linha->origem);
        $this->assertEquals(70, $linha->valor);

        $tela->callTableAction('cobrarTodas')->assertTableActionHidden('cancelar', $linha->fresh()->forceFill(['status' => 'paga']));
        $this->assertSame('cobrada', $linha->fresh()->status);

        Livewire::test(TenantResource\Pages\ListTenants::class)->filterTable('taxas_pendentes', true)->assertCanSeeTableRecords([$t]);
    }
}
