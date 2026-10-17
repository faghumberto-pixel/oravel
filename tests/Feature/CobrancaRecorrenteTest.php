<?php

namespace Tests\Feature;

use App\Filament\Pages\CobrancaAutomatica;
use App\Models\AccountReceivable;
use App\Models\BillingPlan;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantAsaasSetting;
use App\Models\User;
use App\Services\Cobranca\CobrancaService;
use App\Services\Cobranca\GeradorDeCobrancas;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 8B: contas geradas dos Planos de Cobrança e cobrança automática no Asaas da empresa. */
class CobrancaRecorrenteTest extends TestCase
{
    use DatabaseTransactions;

    private const SANDBOX = 'https://api-sandbox.asaas.com/v3';

    private function tenant(bool $complemento = true): Tenant
    {
        $plan = Plan::create(['name' => 'Plano '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_account_receivables' => true, TenantAsaasSetting::FEATURE => $complemento]]);

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
        return TenantAsaasSetting::withoutGlobalScopes()->create($extra + ['tenant_id' => $t->id, 'enabled' => true, 'api_key' => 'KEY-EMPRESA', 'gerar_recorrentes' => true, 'cobrar_automaticamente' => true, 'dias_antecedencia' => 10]);
    }

    private function cliente(Tenant $t): Client
    {
        return Client::create(['tenant_id' => $t->id, 'name' => 'Cliente '.uniqid(), 'cpf_cnpj' => '11.222.333/0001-81', 'asaas_customer_id' => 'cus_1']);
    }

    private function plano(Tenant $t, array $extra = []): BillingPlan
    {
        return BillingPlan::withoutGlobalScopes()->create($extra + ['tenant_id' => $t->id, 'client_id' => $this->cliente($t)->id, 'frequency' => 'monthly', 'amount' => 800, 'due_day' => 15, 'active' => true]);
    }

    private function contrato(Tenant $t, array $extra = []): Contract
    {
        return Contract::withoutEvents(fn () => Contract::create($extra + ['tenant_id' => $t->id, 'client_id' => $this->cliente($t)->id, 'contract_number' => 'CT-'.uniqid(), 'start_date' => '2026-10-01', 'price' => 800, 'status' => 'Ativo']));
    }

    private function fakeAsaas(): void
    {
        Http::fake([self::SANDBOX.'/payments' => fn () => Http::response(['id' => 'pay_'.uniqid('', true), 'status' => 'PENDING', 'invoiceUrl' => 'https://x/i', 'bankSlipUrl' => 'https://x/b'], 200)]);
    }

    private function gerar(TenantAsaasSetting $c, string $hoje): int
    {
        return app(GeradorDeCobrancas::class)->gerarRecorrentes($c, Carbon::parse($hoje));
    }

    public function test_migration_adds_automation_columns_and_is_reversible(): void
    {
        $this->assertTrue(Schema::hasColumn('tenant_asaas_settings', 'gerar_recorrentes'));
        $this->assertTrue(Schema::hasColumn('account_receivables', 'cobranca_tentativas'));

        $m = require base_path('database/migrations/2026_11_24_100000_add_automacao_to_cobranca.php');
        $m->down();
        $this->assertFalse(Schema::hasColumn('account_receivables', 'cobranca_erro'));
        $m->up();
        $this->assertTrue(Schema::hasColumn('tenant_asaas_settings', 'dias_antecedencia'));
    }

    public function test_monthly_plan_generates_one_account_per_period_inside_the_window_without_duplicates(): void
    {
        $t = $this->tenant();
        $c = $this->config($t);
        $plano = $this->plano($t);

        $this->assertSame(1, $this->gerar($c, '2026-11-10'), 'vence dia 15: está na janela de 10 dias');
        $this->assertSame(0, $this->gerar($c, '2026-11-10'), 'rodar de novo não duplica');
        $this->assertSame(0, $this->gerar($c, '2026-11-11'));

        $conta = AccountReceivable::withoutGlobalScopes()->where('billing_plan_id', $plano->id)->firstOrFail();
        $this->assertSame('2026-11-15', $conta->due_date->toDateString());
        $this->assertEquals(800, $conta->amount);
        $this->assertSame('pendente', $conta->status);
        $this->assertSame($plano->client_id, $conta->client_id);
        $this->assertStringContainsString('11/2026', $conta->description);

        $this->assertSame(0, $this->gerar($c, '2026-11-20'), 'vencimento já passou e nada novo na janela');
        $this->assertSame(1, $this->gerar($c, '2026-12-06'), 'dezembro entra na janela em 6/12');
        $this->assertSame(2, AccountReceivable::withoutGlobalScopes()->where('billing_plan_id', $plano->id)->count());
    }

    public function test_window_outside_the_horizon_generates_nothing(): void
    {
        $t = $this->tenant();
        $c = $this->config($t);
        $this->plano($t);

        $this->assertSame(0, $this->gerar($c, '2026-11-01'), '15/11 está a 14 dias: fora dos 10 de antecedência');
        $c->update(['dias_antecedencia' => 20]);
        $this->assertSame(1, $this->gerar($c->fresh(), '2026-11-01'));
    }

    public function test_quarterly_and_annual_follow_the_contract_start(): void
    {
        $t = $this->tenant();
        $c = $this->config($t);
        $contrato = $this->contrato($t, ['start_date' => '2026-10-01']);
        $tri = $this->plano($t, ['frequency' => 'quarterly', 'contract_id' => $contrato->id, 'client_id' => $contrato->client_id]);
        $ano = $this->plano($t, ['frequency' => 'annual', 'contract_id' => $contrato->id, 'client_id' => $contrato->client_id, 'due_day' => 20]);

        $this->assertSame(2, $this->gerar($c, '2026-10-12'), 'outubro é o mês-âncora: os dois cobram');
        $this->assertSame(0, $this->gerar($c, '2026-11-10'));
        $this->assertSame(0, $this->gerar($c, '2026-12-10'));
        $this->assertSame(1, $this->gerar($c, '2027-01-10'), 'janeiro: só o trimestral');
        $this->assertSame(2, $this->gerar($c, '2027-10-12'), 'um ano depois: o trimestral e o anual voltam a cobrar');
        $this->assertSame(2, AccountReceivable::withoutGlobalScopes()->where('billing_plan_id', $ano->id)->count());
        $this->assertSame(3, AccountReceivable::withoutGlobalScopes()->where('billing_plan_id', $tri->id)->count(), 'out/26, jan/27 e out/27 (rodadas feitas)');
    }

    public function test_due_day_is_clamped_to_the_short_month(): void
    {
        $t = $this->tenant();
        $c = $this->config($t);
        $this->plano($t, ['due_day' => 31]);

        $this->assertSame(1, $this->gerar($c, '2027-02-20'));
        $this->assertSame('2027-02-28', AccountReceivable::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail()->due_date->toDateString());
    }

    public function test_inactive_plans_without_client_and_ended_or_draft_contracts_are_skipped(): void
    {
        $t = $this->tenant();
        $c = $this->config($t);
        $this->plano($t, ['active' => false]);
        $this->plano($t, ['client_id' => null]);
        $this->plano($t, ['contract_id' => $this->contrato($t, ['status' => 'Encerrado'])->id]);
        $this->plano($t, ['contract_id' => $this->contrato($t, ['status' => 'Draft'])->id]);
        $this->plano($t, ['contract_id' => $this->contrato($t, ['end_date' => '2026-11-01'])->id]);

        $this->assertSame(0, $this->gerar($c, '2026-11-10'));
    }

    public function test_one_company_never_generates_for_another(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $ca = $this->config($a);
        $this->config($b);
        $deB = $this->plano($b);

        $this->assertSame(0, $this->gerar($ca, '2026-11-10'));
        $this->assertSame(0, AccountReceivable::withoutGlobalScopes()->where('billing_plan_id', $deB->id)->count());
    }

    public function test_pending_accounts_get_a_charge_and_the_rest_is_left_alone(): void
    {
        $this->fakeAsaas();
        $t = $this->tenant();
        $c = $this->config($t);
        $cli = $this->cliente($t);
        $mk = fn (array $x) => AccountReceivable::withoutGlobalScopes()->create($x + ['tenant_id' => $t->id, 'client_id' => $cli->id, 'description' => 'C', 'amount' => 100, 'due_date' => now()->addDays(5), 'status' => 'pendente']);
        $ok = $mk([]);
        $atrasada = $mk(['due_date' => now()->subDays(3), 'status' => 'atrasado']);
        $longe = $mk(['due_date' => now()->addDays(40)]);
        $paga = $mk(['status' => 'pago']);
        $semCliente = $mk(['client_id' => null]);
        $jaTem = $mk(['asaas_payment_id' => 'pay_antiga']);

        $r = app(GeradorDeCobrancas::class)->enviarPendentes($c);

        $this->assertSame(['enviadas' => 2, 'falhas' => 0], $r);
        $this->assertNotNull($ok->fresh()->asaas_payment_id);
        $this->assertNotNull($atrasada->fresh()->asaas_payment_id);
        foreach ([$longe, $paga, $semCliente] as $conta) {
            $this->assertNull($conta->fresh()->asaas_payment_id);
        }
        $this->assertSame('pay_antiga', $jaTem->fresh()->asaas_payment_id);
        Http::assertSentCount(2);
    }

    public function test_failures_are_recorded_retried_with_limits_and_admins_are_warned_once(): void
    {
        $falhando = true;
        Http::fake([self::SANDBOX.'/payments' => function () use (&$falhando) {
            return $falhando
                ? Http::response(['errors' => [['description' => 'Saldo insuficiente']]], 400)
                : Http::response(['id' => 'pay_ok', 'status' => 'PENDING'], 200);
        }]);
        $t = $this->tenant();
        $admin = $this->admin($t);
        $c = $this->config($t);
        $conta = AccountReceivable::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'client_id' => $this->cliente($t)->id, 'description' => 'Fatura X', 'amount' => 100, 'due_date' => now()->addDays(3), 'status' => 'pendente']);
        $servico = app(GeradorDeCobrancas::class);

        $this->assertSame(['enviadas' => 0, 'falhas' => 1], $servico->enviarPendentes($c));
        $this->assertSame(1, $conta->fresh()->cobranca_tentativas);
        $this->assertSame('Saldo insuficiente', $conta->fresh()->cobranca_erro);

        $this->assertSame(['enviadas' => 0, 'falhas' => 0], $servico->enviarPendentes($c), 'não insiste na mesma hora');

        for ($i = 2; $i <= 5; $i++) {
            $this->travel($i * 2)->hours();
            $servico->enviarPendentes($c);
        }

        $this->assertSame(5, $conta->fresh()->cobranca_tentativas);
        $this->assertSame(1, DB::table('notifications')->where('notifiable_id', $admin->id)->count(), 'aviso ao chegar no limite, uma vez só');

        $this->travel(48)->hours();
        $this->assertSame(['enviadas' => 0, 'falhas' => 0], $servico->enviarPendentes($c), 'depois do limite para de tentar');

        // "Gerar cobrança" manual (corrigido o problema) limpa o erro.
        $falhando = false;
        app(CobrancaService::class)->gerar($conta->fresh());
        $this->assertSame(0, $conta->fresh()->cobranca_tentativas);
        $this->assertNull($conta->fresh()->cobranca_erro);
    }

    public function test_command_runs_the_whole_flow_respects_the_switches_and_skips_unready_companies(): void
    {
        $this->fakeAsaas();
        $t = $this->tenant();
        $c = $this->config($t);
        $this->plano($t);
        $semConfig = $this->tenant();
        $this->plano($semConfig);
        $semComplemento = $this->tenant(false);
        $this->config($semComplemento);
        $this->plano($semComplemento);

        $this->artisan('cobranca:processar', ['--hoje' => '2026-11-10'])->assertSuccessful();

        $conta = AccountReceivable::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertNotNull($conta->asaas_payment_id, 'plano → conta → cobrança no Asaas');
        $this->assertSame(0, AccountReceivable::withoutGlobalScopes()->where('tenant_id', $semConfig->id)->count());
        $this->assertSame(0, AccountReceivable::withoutGlobalScopes()->where('tenant_id', $semComplemento->id)->count());
        Http::assertSent(fn ($r) => $r->hasHeader('access_token', 'KEY-EMPRESA'));

        // Chaves desligadas: nada acontece.
        $c->update(['gerar_recorrentes' => false, 'cobrar_automaticamente' => false]);
        AccountReceivable::withoutGlobalScopes()->where('tenant_id', $t->id)->delete();
        Http::fake();
        $this->artisan('cobranca:processar', ['--hoje' => '2026-12-06'])->assertSuccessful();
        $this->assertSame(0, AccountReceivable::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
        Http::assertNothingSent();

        // Só gerar as contas, sem cobrar no Asaas.
        $c->update(['gerar_recorrentes' => true]);
        $this->artisan('cobranca:processar', ['--hoje' => '2026-12-06', '--tenant-id' => $t->id])->assertSuccessful();
        $this->assertNull(AccountReceivable::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail()->asaas_payment_id);
        Http::assertNothingSent();
    }

    public function test_invoiced_contract_receivables_are_also_charged_automatically(): void
    {
        $this->fakeAsaas();
        $t = $this->tenant();
        $c = $this->config($t, ['gerar_recorrentes' => false]);
        $contrato = $this->contrato($t);
        $conta = AccountReceivable::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'client_id' => $contrato->client_id, 'contract_id' => $contrato->id, 'description' => 'Medição #1', 'amount' => 5400, 'due_date' => now()->addDays(15), 'status' => 'pendente']);

        $this->assertSame(0, app(GeradorDeCobrancas::class)->enviarPendentes($c)['enviadas'], '15 dias de prazo: fora da antecedência de 10');
        $c->update(['dias_antecedencia' => 15]);
        $this->assertSame(1, app(GeradorDeCobrancas::class)->enviarPendentes($c->fresh())['enviadas']);
        $this->assertNotNull($conta->fresh()->asaas_payment_id);
    }

    public function test_settings_page_saves_the_automation_switches_and_the_job_is_scheduled(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin($t));

        Livewire::test(CobrancaAutomatica::class)
            ->fillForm(['ambiente' => 'sandbox', 'api_key' => 'K', 'forma_padrao' => 'UNDEFINED', 'gerar_recorrentes' => true, 'cobrar_automaticamente' => true, 'dias_antecedencia' => 7])
            ->call('salvar');

        $c = TenantAsaasSetting::daEmpresa($t->id);
        $this->assertTrue($c->gerar_recorrentes);
        $this->assertTrue($c->cobrar_automaticamente);
        $this->assertSame(7, $c->dias_antecedencia);

        Artisan::call('schedule:list');
        $this->assertStringContainsString('cobranca:processar', Artisan::output());
    }
}
