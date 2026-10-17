<?php

namespace Tests\Feature;

use App\Console\Commands\SyncAsaasPaymentStatus;
use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Pages\CobrancaAutomatica;
use App\Filament\Resources\AccountReceivableResource;
use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantAsaasSetting;
use App\Models\User;
use App\Services\Cobranca\CobrancaException;
use App\Services\Cobranca\CobrancaService;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 8A: cobrança automática pela conta Asaas DA EMPRESA (nunca a da Oravel). */
class CobrancaAutomaticaTest extends TestCase
{
    use DatabaseTransactions;

    private const SANDBOX = 'https://api-sandbox.asaas.com/v3';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.api_key' => 'CHAVE-DA-ORAVEL']);
    }

    private function tenant(bool $complemento = true): Tenant
    {
        $features = ['tabela_account_receivables' => true, 'tabela_clients' => true, TenantAsaasSetting::FEATURE => $complemento];
        $plan = Plan::create(['name' => 'Plano '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);

        return Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function admin(Tenant $t): User
    {
        $u = User::create(['name' => 'Admin '.uniqid(), 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    private function config(Tenant $t, array $extra = []): TenantAsaasSetting
    {
        return TenantAsaasSetting::withoutGlobalScopes()->create($extra + ['tenant_id' => $t->id, 'enabled' => true, 'ambiente' => 'sandbox', 'api_key' => 'CHAVE-DA-EMPRESA']);
    }

    private function conta(Tenant $t, array $extra = [], array $cliente = []): AccountReceivable
    {
        $c = Client::create($cliente + ['tenant_id' => $t->id, 'name' => 'Cliente '.uniqid(), 'cpf_cnpj' => '11.222.333/0001-81', 'email' => 'c@x.com']);

        return AccountReceivable::create($extra + ['tenant_id' => $t->id, 'client_id' => $c->id, 'description' => 'Locação out/26', 'amount' => 1500, 'due_date' => now()->addDays(10), 'status' => 'pendente']);
    }

    private function fakeAsaas(array $payment = []): void
    {
        Http::fake([
            self::SANDBOX.'/customers?*' => Http::response(['data' => []], 200),
            self::SANDBOX.'/customers' => Http::response(['id' => 'cus_1'], 200),
            self::SANDBOX.'/payments' => Http::response($payment + ['id' => 'pay_1', 'status' => 'PENDING', 'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_1', 'bankSlipUrl' => 'https://sandbox.asaas.com/b/pay_1'], 200),
            self::SANDBOX.'/payments/*' => Http::response(['id' => 'pay_1', 'status' => 'RECEIVED', 'paymentDate' => '2026-11-05'], 200),
            self::SANDBOX.'/finance/balance' => Http::response(['balance' => 123.45], 200),
        ]);
    }

    public function test_migration_creates_the_tables_and_columns_and_is_reversible(): void
    {
        $this->assertTrue(Schema::hasTable('tenant_asaas_settings'));
        $this->assertTrue(Schema::hasColumn('clients', 'asaas_customer_id'));
        $this->assertTrue(Schema::hasColumn('account_receivables', 'asaas_status'));

        $migration = require base_path('database/migrations/2026_11_23_100000_create_tenant_asaas_settings.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('tenant_asaas_events'));
        $this->assertFalse(Schema::hasColumn('clients', 'asaas_customer_id'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('tenant_asaas_events'));
    }

    public function test_api_key_is_encrypted_hidden_and_the_webhook_token_is_generated(): void
    {
        $t = $this->tenant();
        $c = $this->config($t);

        $this->assertSame('CHAVE-DA-EMPRESA', $c->fresh()->api_key);
        $this->assertStringNotContainsString('CHAVE-DA-EMPRESA', (string) DB::table('tenant_asaas_settings')->where('id', $c->id)->value('api_key'));
        $this->assertArrayNotHasKey('api_key', $c->toArray());
        $this->assertArrayNotHasKey('webhook_token', $c->toArray());
        $this->assertGreaterThanOrEqual(48, strlen($c->webhook_token));
        $this->assertTrue($c->pronta());

        $sem = $this->tenant(false);
        $this->assertFalse($this->config($sem)->pronta(), 'sem o complemento no contrato não cobra');
        $this->assertFalse($this->config($this->tenant(), ['enabled' => false])->pronta());
    }

    public function test_generating_a_charge_uses_the_company_account_never_the_platform_key(): void
    {
        $this->fakeAsaas();
        $t = $this->tenant();
        $this->config($t, ['multa_percentual' => 2, 'juros_mensal' => 1]);
        $conta = $this->conta($t);

        $conta = app(CobrancaService::class)->gerar($conta);

        $this->assertSame('pay_1', $conta->asaas_payment_id);
        $this->assertSame('https://sandbox.asaas.com/i/pay_1', $conta->asaas_invoice_url);
        $this->assertSame('https://sandbox.asaas.com/b/pay_1', $conta->asaas_boleto_url);
        $this->assertSame('PENDING', $conta->asaas_status);
        $this->assertSame('cus_1', $conta->client->fresh()->asaas_customer_id);

        Http::assertSent(function ($r) use ($conta) {
            if (! str_ends_with($r->url(), '/payments')) {
                return false;
            }

            return $r->hasHeader('access_token', 'CHAVE-DA-EMPRESA')
                && $r['customer'] === 'cus_1' && $r['value'] === 1500.0 && $r['billingType'] === 'UNDEFINED'
                && $r['externalReference'] === $conta->id && $r['fine']['value'] === 2.0 && $r['interest']['value'] === 1.0
                && $r['dueDate'] === now()->addDays(10)->toDateString();
        });
        Http::assertNotSent(fn ($r) => $r->hasHeader('access_token', 'CHAVE-DA-ORAVEL'));
    }

    public function test_it_is_idempotent_reuses_the_customer_and_pushes_overdue_to_today(): void
    {
        $this->fakeAsaas();
        $t = $this->tenant();
        $this->config($t);
        $cliente = Client::create(['tenant_id' => $t->id, 'name' => 'Cliente Repetido', 'cpf_cnpj' => '123.456.789-09', 'asaas_customer_id' => 'cus_existente']);
        $a = AccountReceivable::create(['tenant_id' => $t->id, 'client_id' => $cliente->id, 'description' => 'A', 'amount' => 100, 'due_date' => now()->subDays(5), 'status' => 'atrasado']);

        $servico = app(CobrancaService::class);
        $servico->gerar($a);
        $servico->gerar($a->refresh());   // segunda vez: nada novo

        Http::assertSentCount(1);   // só /payments: cliente já existia no Asaas
        Http::assertSent(fn ($r) => $r['customer'] === 'cus_existente' && $r['dueDate'] === now()->toDateString());
    }

    public function test_existing_customer_in_asaas_is_found_by_document_instead_of_duplicated(): void
    {
        Http::fake([
            self::SANDBOX.'/customers?*' => Http::response(['data' => [['id' => 'cus_achado']]], 200),
            self::SANDBOX.'/payments' => Http::response(['id' => 'pay_9', 'status' => 'PENDING'], 200),
        ]);
        $t = $this->tenant();
        $this->config($t);

        $conta = app(CobrancaService::class)->gerar($this->conta($t));

        $this->assertSame('cus_achado', $conta->client->fresh()->asaas_customer_id);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/customers'));
    }

    public function test_validations_explain_what_is_missing(): void
    {
        $this->fakeAsaas();
        $t = $this->tenant();
        $this->config($t);
        $servico = app(CobrancaService::class);

        $casos = [
            'sem CPF/CNPJ' => [fn () => $this->conta($t, [], ['cpf_cnpj' => null]), 'CPF ou CNPJ'],
            'já recebida' => [fn () => $this->conta($t, ['status' => 'pago']), 'já foi recebida'],
            'valor zero' => [fn () => $this->conta($t, ['amount' => 0]), 'maior que zero'],
        ];

        foreach ($casos as $nome => [$fabrica, $trecho]) {
            try {
                $servico->gerar($fabrica());
                $this->fail("{$nome}: deveria recusar");
            } catch (CobrancaException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage(), $nome);
            }
        }

        $semComplemento = $this->tenant(false);
        $this->config($semComplemento);
        $this->expectException(CobrancaException::class);
        $servico->gerar($this->conta($semComplemento));
    }

    public function test_asaas_errors_become_a_readable_message_and_leave_the_account_untouched(): void
    {
        Http::fake([self::SANDBOX.'/customers?*' => Http::response(['data' => []], 200), self::SANDBOX.'/customers' => Http::response(['id' => 'cus_1'], 200),
            self::SANDBOX.'/payments' => Http::response(['errors' => [['description' => 'Chave de API inválida']]], 401)]);
        $t = $this->tenant();
        $this->config($t);
        $conta = $this->conta($t);

        try {
            app(CobrancaService::class)->gerar($conta);
            $this->fail('deveria lançar');
        } catch (CobrancaException $e) {
            $this->assertSame('Chave de API inválida', $e->getMessage());
        }

        $this->assertNull($conta->fresh()->asaas_payment_id);
    }

    public function test_cancel_and_sync(): void
    {
        $this->fakeAsaas();
        Http::fake([self::SANDBOX.'/payments/pay_1' => Http::sequence()->push(['id' => 'pay_1', 'status' => 'RECEIVED', 'paymentDate' => '2026-11-05'], 200)->push([], 200)]);
        $t = $this->tenant();
        $this->config($t);
        $servico = app(CobrancaService::class);

        $paga = $servico->sincronizar($this->conta($t, ['asaas_payment_id' => 'pay_1']));
        $this->assertSame('pago', $paga->status);
        $this->assertSame('2026-11-05', $paga->payment_date->toDateString());

        $aberta = $this->conta($t, ['asaas_payment_id' => 'pay_2', 'asaas_invoice_url' => 'x']);
        Http::fake([self::SANDBOX.'/payments/pay_2' => Http::response([], 200)]);
        $servico->cancelar($aberta);
        $this->assertNull($aberta->fresh()->asaas_payment_id);
        $this->assertSame('DELETED', $aberta->fresh()->asaas_status);

        $this->expectException(CobrancaException::class);
        $servico->cancelar($paga);
    }

    // ---------------------------------------------------------------- webhook

    private function evento(Tenant $t, string $event, array $payment, ?string $id = null, ?string $token = null)
    {
        $token ??= TenantAsaasSetting::daEmpresa($t->id)?->webhook_token;

        return $this->postJson('/api/webhooks/asaas-empresa/'.$t->id, ['id' => $id ?? 'evt_'.uniqid(), 'event' => $event, 'payment' => $payment], ['asaas-access-token' => (string) $token]);
    }

    public function test_webhook_rejects_wrong_token_and_unknown_company_the_same_way(): void
    {
        $t = $this->tenant();
        $this->config($t);

        $this->evento($t, 'PAYMENT_RECEIVED', ['id' => 'p'], null, 'errado')->assertForbidden();
        $this->postJson('/api/webhooks/asaas-empresa/'.Str::uuid(), [], ['asaas-access-token' => 'x'])->assertForbidden();
        $this->postJson('/api/webhooks/asaas-empresa/'.$t->id, [])->assertForbidden();
    }

    public function test_webhook_updates_the_account_and_is_idempotent(): void
    {
        $t = $this->tenant();
        $this->config($t);
        $conta = $this->conta($t, ['asaas_payment_id' => 'pay_1']);

        $this->evento($t, 'PAYMENT_RECEIVED', ['id' => 'pay_1', 'status' => 'RECEIVED', 'paymentDate' => '2026-11-06'], 'evt_1')->assertOk();
        $this->assertSame('pago', $conta->fresh()->status);
        $this->assertSame('2026-11-06', $conta->fresh()->payment_date->toDateString());

        // Mesmo evento reenviado: não reaplica (o usuário reabriu a conta e o reenvio não pode pagar de novo).
        AccountReceivable::whereKey($conta->id)->update(['status' => 'pendente', 'payment_date' => null]);
        $this->evento($t, 'PAYMENT_RECEIVED', ['id' => 'pay_1', 'status' => 'RECEIVED'], 'evt_1')->assertOk();
        $this->assertSame('pendente', $conta->fresh()->status);
        $this->assertSame(1, DB::table('tenant_asaas_events')->where('tenant_id', $t->id)->where('event_id', 'evt_1')->count());
    }

    public function test_webhook_overdue_deleted_and_refunded(): void
    {
        $t = $this->tenant();
        $this->config($t);
        $atraso = $this->conta($t, ['asaas_payment_id' => 'p_o']);
        $apagada = $this->conta($t, ['asaas_payment_id' => 'p_d', 'asaas_invoice_url' => 'x']);
        $estornada = $this->conta($t, ['asaas_payment_id' => 'p_r', 'status' => 'pago', 'payment_date' => now()]);

        $this->evento($t, 'PAYMENT_OVERDUE', ['id' => 'p_o', 'status' => 'OVERDUE'])->assertOk();
        $this->evento($t, 'PAYMENT_DELETED', ['id' => 'p_d', 'status' => 'DELETED'])->assertOk();
        $this->evento($t, 'PAYMENT_REFUNDED', ['id' => 'p_r', 'status' => 'REFUNDED'])->assertOk();

        $this->assertSame('atrasado', $atraso->fresh()->status);
        $this->assertNull($apagada->fresh()->asaas_payment_id);
        $this->assertNull($apagada->fresh()->asaas_invoice_url);
        $this->assertNotSame('pago', $estornada->fresh()->status);
        $this->assertNull($estornada->fresh()->payment_date);
    }

    public function test_webhook_never_touches_another_companys_account(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $this->config($a);
        $this->config($b);
        $deB = $this->conta($b, ['asaas_payment_id' => 'pay_igual']);

        $this->evento($a, 'PAYMENT_RECEIVED', ['id' => 'pay_igual', 'status' => 'RECEIVED'])->assertOk();

        $this->assertSame('pendente', $deB->fresh()->status);
        // E o token da empresa A não vale na URL da B.
        $this->evento($b, 'PAYMENT_RECEIVED', ['id' => 'pay_igual', 'status' => 'RECEIVED'], null, TenantAsaasSetting::daEmpresa($a->id)->webhook_token)->assertForbidden();
    }

    public function test_webhook_is_ignored_when_the_module_is_off(): void
    {
        $t = $this->tenant(false);
        $this->config($t);
        $conta = $this->conta($t, ['asaas_payment_id' => 'pay_1']);

        $this->evento($t, 'PAYMENT_RECEIVED', ['id' => 'pay_1', 'status' => 'RECEIVED'])->assertOk()->assertJson(['ignorado' => 'modulo_desligado']);
        $this->assertSame('pendente', $conta->fresh()->status);
    }

    // ---------------------------------------------------------------- telas, comando e pacotes

    public function test_settings_page_access_save_and_connection_test(): void
    {
        $t = $this->tenant();
        $admin = $this->admin($t);
        $this->actingAs($admin);
        $this->fakeAsaas();

        Livewire::test(CobrancaAutomatica::class)->assertSuccessful()
            ->fillForm(['ambiente' => 'sandbox', 'api_key' => 'MINHA-CHAVE', 'forma_padrao' => 'PIX', 'multa_percentual' => 2])
            ->call('salvar');

        $c = TenantAsaasSetting::daEmpresa($t->id);
        $this->assertSame('MINHA-CHAVE', $c->api_key);
        $this->assertFalse($c->enabled, 'chave nova só liga depois de testar');
        $this->assertSame('PIX', $c->forma_padrao);

        Livewire::test(CobrancaAutomatica::class)->fillForm(['ambiente' => 'sandbox', 'forma_padrao' => 'PIX'])->call('testarEAtivar');
        $this->assertTrue($c->fresh()->enabled);
        $this->assertTrue($c->fresh()->last_test_ok);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/finance/balance') && $r->hasHeader('access_token', 'MINHA-CHAVE'));

        Livewire::test(CobrancaAutomatica::class)->call('desligar');
        $this->assertFalse($c->fresh()->enabled);
    }

    public function test_invalid_key_does_not_turn_it_on_and_page_is_gated(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin($t));
        Http::fake([self::SANDBOX.'/finance/balance' => Http::response(['errors' => [['description' => 'Chave inválida']]], 401)]);

        Livewire::test(CobrancaAutomatica::class)->fillForm(['ambiente' => 'sandbox', 'api_key' => 'RUIM', 'forma_padrao' => 'UNDEFINED'])->call('testarEAtivar');

        $c = TenantAsaasSetting::daEmpresa($t->id);
        $this->assertFalse($c->enabled);
        $this->assertFalse($c->last_test_ok);
        $this->assertSame('Chave inválida', $c->last_error);

        $this->assertTrue(CobrancaAutomatica::canAccess());
        $semComplemento = $this->tenant(false);
        $this->actingAs($this->admin($semComplemento));
        $this->assertFalse(CobrancaAutomatica::canAccess());
    }

    public function test_receivable_actions_generate_cancel_and_hide_without_the_feature(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin($t));
        $this->config($t);
        $this->fakeAsaas();
        $conta = $this->conta($t);

        $this->assertTrue(AccountReceivableResource::cobrancaLigada());
        Livewire::test(AccountReceivableResource\Pages\ManageAccountReceivables::class)
            ->assertTableActionVisible('gerarCobranca', $conta)->assertTableActionHidden('cancelarCobranca', $conta)
            ->callTableAction('gerarCobranca', $conta);
        $this->assertSame('pay_1', $conta->fresh()->asaas_payment_id);

        Livewire::test(AccountReceivableResource\Pages\ManageAccountReceivables::class)
            ->assertTableActionHidden('gerarCobranca', $conta->fresh())->assertTableActionVisible('cancelarCobranca', $conta->fresh())
            ->assertTableActionVisible('verCobranca', $conta->fresh());

        $sem = $this->tenant(false);
        $this->actingAs($this->admin($sem));
        $this->assertFalse(AccountReceivableResource::cobrancaLigada());
    }

    public function test_verification_command_uses_each_companys_key_and_skips_the_others(): void
    {
        Http::fake([self::SANDBOX.'/payments/*' => Http::response(['id' => 'pay_1', 'status' => 'RECEIVED', 'paymentDate' => '2026-11-07'], 200)]);
        $com = $this->tenant();
        $this->config($com, ['api_key' => 'KEY-COM']);
        $a = $this->conta($com, ['asaas_payment_id' => 'pay_1']);
        $sem = $this->tenant();   // tem conta com cobrança mas nunca configurou o Asaas
        $b = $this->conta($sem, ['asaas_payment_id' => 'pay_x']);

        $this->artisan('asaas:sync-payment-status')->assertSuccessful();

        $this->assertSame('pago', $a->fresh()->status);
        $this->assertSame('pendente', $b->fresh()->status);
        Http::assertNotSent(fn ($r) => $r->hasHeader('access_token', 'CHAVE-DA-ORAVEL'));
        $this->assertTrue(class_exists(SyncAsaasPaymentStatus::class));
    }

    public function test_the_legacy_command_that_used_the_platform_account_is_gone(): void
    {
        $this->assertArrayNotHasKey('asaas:sync-receivables', Artisan::all());
    }

    public function test_the_complement_is_not_part_of_any_package_and_shows_in_its_own_group(): void
    {
        foreach (array_keys(Pacotes::opcoes()) as $codigo) {
            $this->assertNotContains(TenantAsaasSetting::FEATURE, Pacotes::features($codigo), "{$codigo} não pode incluir o complemento");
        }

        $grupos = ContratoResource::groupedFeatureOptions();
        $this->assertArrayHasKey(TenantAsaasSetting::FEATURE, $grupos['Complementos'] ?? []);
    }
}
