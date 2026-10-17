<?php

namespace Tests\Feature;

use App\Console\Commands\SincronizarComplementos;
use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Central\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Central\Resources\TenantResource\RelationManagers\ComplementosRelationManager;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantComplemento;
use App\Models\TenantEvent;
use App\Models\User;
use App\Services\ComplementoException;
use App\Services\ComplementoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 8D: complementos cobrados na assinatura recorrente, com fidelidade de 12 meses. */
class ComplementosTest extends TestCase
{
    use DatabaseTransactions;

    private const FEATURE = 'modulo_cobranca_automatica';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.api_key' => 'KEY-ORAVEL', 'services.asaas.base_url' => 'https://asaas.test/v3']);
        $this->asaasFalhando = false;
        Http::fake(['asaas.test/v3/subscriptions/*' => fn () => $this->asaasFalhando ? Http::response(['errors' => []], 500) : Http::response(['id' => 'sub_1'], 200)]);
    }

    private bool $asaasFalhando = false;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function plano(array $extra = []): Plan
    {
        return Plan::create($extra + ['name' => 'Plano '.uniqid(), 'price' => 500, 'base_price' => 500, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients' => true]]);
    }

    private function tenant(Plan $plano, array $extra = []): Tenant
    {
        return Tenant::create($extra + ['name' => 'Empresa '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active', 'mrr_value' => 500, 'asaas_subscription_id' => 'sub_1']);
    }

    private function superAdmin(): User
    {
        $u = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'is_approved' => true]);
        config(['oravel.super_admins' => [$u->email]]);

        return $u;
    }

    private function servico(): ComplementoService
    {
        return app(ComplementoService::class);
    }

    public function test_migration_adds_the_table_and_columns_and_is_reversible(): void
    {
        $this->assertTrue(Schema::hasTable('tenant_complementos'));
        $this->assertTrue(Schema::hasColumn('tenants', 'mrr_base'));
        $this->assertSame(500.0, (float) $this->tenant($this->plano())->mrr_base, 'tenant novo nasce com base = mrr');

        $m = require base_path('database/migrations/2026_11_26_100000_create_tenant_complementos.php');
        $m->down();
        $this->assertFalse(Schema::hasTable('tenant_complementos'));
        $m->up();
        $this->assertTrue(Schema::hasColumn('tenants', 'mrr_pendente_sincronizar'));
    }

    public function test_contracting_clones_the_shared_showcase_plan_adds_the_value_and_updates_asaas(): void
    {
        $vitrine = $this->plano(['campaign_tag' => 'vitrine', 'pacote' => 'servicos']);
        $cliente = $this->tenant($vitrine);
        $outro = $this->tenant($vitrine);

        $c = $this->servico()->contratar($cliente, self::FEATURE, 200);

        $cliente->refresh();
        $this->assertNotSame($vitrine->id, $cliente->plan_id, 'contrato próprio, copiado da vitrine');
        $this->assertTrue($cliente->plan->features[self::FEATURE]);
        $this->assertNull($cliente->plan->campaign_tag);
        $this->assertSame('servicos', $cliente->plan->pacote);
        $this->assertSame($vitrine->id, $outro->fresh()->plan_id, 'o outro cliente continua na vitrine');
        $this->assertArrayNotHasKey(self::FEATURE, $vitrine->fresh()->features);

        $this->assertSame('ativo', $c->status);
        $this->assertSame(now()->addMonths(12)->toDateString(), $c->fidelidade_ate->toDateString());
        $this->assertEquals(620, $cliente->mrr_value);
        $this->assertEquals(500, $cliente->mrr_base);
        $this->assertFalse($cliente->mrr_pendente_sincronizar);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/subscriptions/sub_1') && $r['value'] === 620.0);
        $this->assertSame(1, TenantEvent::withoutGlobalScopes()->where('tenant_id', $cliente->id)->where('event_type', TenantEvent::COMPLEMENTO_CONTRATADO)->count());
    }

    public function test_a_private_contract_is_changed_in_place(): void
    {
        $proprio = $this->plano();
        $cliente = $this->tenant($proprio);

        $this->servico()->contratar($cliente, self::FEATURE, 80);

        $this->assertSame($proprio->id, $cliente->fresh()->plan_id);
        $this->assertTrue($proprio->fresh()->features[self::FEATURE]);
    }

    public function test_it_refuses_invalid_requests(): void
    {
        $cliente = $this->tenant($this->plano());
        $semAssinatura = $this->tenant($this->plano(), ['asaas_subscription_id' => null]);

        $casos = [
            'valor zero' => [fn () => $this->servico()->contratar($cliente, self::FEATURE, 0), 'valor cheio'],
            'complemento desconhecido' => [fn () => $this->servico()->contratar($cliente, 'modulo_inexistente', 10), 'desconhecido'],
            'sem assinatura recorrente' => [fn () => $this->servico()->contratar($semAssinatura, self::FEATURE, 10), 'assinatura recorrente'],
        ];

        foreach ($casos as $nome => [$acao, $trecho]) {
            try {
                $acao();
                $this->fail("{$nome}: deveria recusar");
            } catch (ComplementoException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage(), $nome);
            }
        }

        $this->servico()->contratar($cliente, self::FEATURE, 10);
        $this->expectException(ComplementoException::class);
        $this->servico()->contratar($cliente, self::FEATURE, 10);
    }

    public function test_cancelling_is_blocked_during_the_loyalty_and_scheduled_for_the_next_cycle_after_it(): void
    {
        Carbon::setTestNow('2026-01-10');
        $cliente = $this->tenant($this->plano());
        $c = $this->servico()->contratar($cliente, self::FEATURE, 200);

        try {
            $this->servico()->cancelar($c);
            $this->fail('dentro da fidelidade não cancela');
        } catch (ComplementoException $e) {
            $this->assertStringContainsString('10/01/2027', $e->getMessage());
        }
        $this->assertSame('ativo', $c->fresh()->status);

        Carbon::setTestNow('2027-01-20');   // fidelidade cumprida
        $c = $this->servico()->cancelar($c->fresh());

        $this->assertSame('encerrando', $c->status);
        $this->assertSame('2027-02-10', $c->encerra_em->toDateString(), 'vale no próximo ciclo (dia 10)');
        $this->assertEquals(620, $cliente->fresh()->mrr_value, 'até lá segue cobrado');
        $this->assertTrue($cliente->fresh()->plan->features[self::FEATURE]);

        $this->assertSame(0, $this->servico()->aplicarEncerramentos(Carbon::parse('2027-02-09')));
        $this->assertSame(1, $this->servico()->aplicarEncerramentos(Carbon::parse('2027-02-10')));

        $cliente->refresh();
        $this->assertSame('encerrado', $c->fresh()->status);
        $this->assertFalse($cliente->plan->features[self::FEATURE]);
        $this->assertEquals(500, $cliente->mrr_value);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['value'] === 500.0);
    }

    public function test_early_termination_is_super_admin_only_needs_a_reason_and_charges_nothing(): void
    {
        $cliente = $this->tenant($this->plano());
        $c = $this->servico()->contratar($cliente, self::FEATURE, 200);
        $comum = User::create(['name' => 'Comum', 'email' => 'c-'.uniqid().'@x.com', 'password' => bcrypt('x'), 'is_approved' => true]);
        $super = $this->superAdmin();

        foreach ([[$comum, 'Pediu para sair', 'Só a Oravel'], [$super, '   ', 'motivo']] as [$quem, $motivo, $trecho]) {
            try {
                $this->servico()->encerrarAntecipado($c, $quem, $motivo);
                $this->fail('deveria recusar');
            } catch (ComplementoException $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }

        $this->servico()->encerrarAntecipado($c, $super, 'Cliente encerrou o contrato');

        $c->refresh();
        $this->assertSame('encerrado', $c->status);
        $this->assertTrue($c->encerramento_antecipado);
        $this->assertSame('Cliente encerrou o contrato', $c->motivo_encerramento);
        $this->assertEquals(500, $cliente->fresh()->mrr_value);
        $this->assertFalse($cliente->fresh()->plan->features[self::FEATURE]);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['value'] === 500.0);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/payments'), 'nenhuma cobrança do saldo');
        $this->assertSame(1, TenantEvent::withoutGlobalScopes()->where('tenant_id', $cliente->id)->where('event_type', TenantEvent::COMPLEMENTO_ENCERRADO_ANTECIPADO)->count());
    }

    public function test_value_waits_while_the_summed_implementation_is_running_and_is_sent_afterwards(): void
    {
        $cliente = $this->tenant($this->plano(), ['implementation_fee' => 900, 'implementation_installments' => 3, 'implementation_billing_mode' => 'somada']);

        $this->servico()->contratar($cliente, self::FEATURE, 200);

        $cliente->refresh();
        $this->assertTrue($cliente->mrr_pendente_sincronizar);
        $this->assertEquals(620, $cliente->mrr_value);
        Http::assertNothingSent();

        $cliente->update(['subscription_reverted_to_base_at' => now()]);
        $r = $this->servico()->rotinaDiaria();

        $this->assertSame(1, $r['enviados']);
        $this->assertFalse($cliente->fresh()->mrr_pendente_sincronizar);
        Http::assertSent(fn ($req) => $req->method() === 'PUT' && $req['value'] === 620.0);
    }

    public function test_asaas_failure_keeps_the_value_pending_retries_and_alerts_after_three_days(): void
    {
        $this->asaasFalhando = true;
        $cliente = $this->tenant($this->plano());

        $this->servico()->contratar($cliente, self::FEATURE, 200);
        $this->assertTrue($cliente->fresh()->mrr_pendente_sincronizar);

        $this->assertSame(1, $this->servico()->rotinaDiaria()['pendentes']);
        $this->assertSame(0, TenantEvent::withoutGlobalScopes()->where('tenant_id', $cliente->id)->where('title', 'like', 'Valor da mensalidade pendente%')->count());

        $this->servico()->rotinaDiaria(now()->addDays(4));
        $this->assertSame(1, TenantEvent::withoutGlobalScopes()->where('tenant_id', $cliente->id)->where('title', 'like', 'Valor da mensalidade pendente%')->count());

        $this->asaasFalhando = false;
        $this->assertSame(1, $this->servico()->rotinaDiaria()['enviados']);
        $this->assertFalse($cliente->fresh()->mrr_pendente_sincronizar);
    }

    public function test_editing_the_total_by_hand_keeps_the_complements_on_top(): void
    {
        $cliente = $this->tenant($this->plano());
        $this->servico()->contratar($cliente, self::FEATURE, 200);

        $cliente->refresh()->update(['mrr_value' => 700]);

        $this->assertEquals(580, $cliente->fresh()->mrr_base);
        $this->assertEquals(700, $cliente->fresh()->mrr_value);

        $outro = $this->tenant($this->plano());
        $this->assertSame(0.0, Tenant::complementosVigentes($outro), 'complemento de uma empresa não conta na outra');
    }

    public function test_daily_routine_warns_about_the_end_of_the_loyalty_once(): void
    {
        Carbon::setTestNow('2026-01-10');
        $cliente = $this->tenant($this->plano());
        $this->servico()->contratar($cliente, self::FEATURE, 200);

        $this->assertSame(0, $this->servico()->rotinaDiaria()['avisos']);
        $this->assertSame(1, $this->servico()->rotinaDiaria(Carbon::parse('2026-12-20'))['avisos']);
        $this->assertSame(0, $this->servico()->rotinaDiaria(Carbon::parse('2026-12-21'))['avisos'], 'aviso uma vez só');
        $this->assertSame(1, $this->servico()->rotinaDiaria(Carbon::parse('2027-01-11'))['avisos'], 'e o de fidelidade concluída');
    }

    public function test_audit_flags_complement_modules_turned_on_without_a_charge(): void
    {
        $manual = $this->plano(['features' => [self::FEATURE => true]]);
        $this->tenant($manual, ['name' => 'Ligado na mão']);
        $certo = $this->tenant($this->plano(), ['name' => 'Contratado direito']);
        $this->servico()->contratar($certo, self::FEATURE, 90);

        $nomes = collect($this->servico()->auditar())->pluck('tenant')->all();

        $this->assertContains('Ligado na mão', $nomes);
        $this->assertNotContains('Contratado direito', $nomes);
        $this->artisan('complementos:auditar')->assertFailed();
    }

    public function test_contract_screen_does_not_let_complements_be_changed_by_hand(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));
        $plano = $this->plano(['features' => ['tabela_clients' => true, self::FEATURE => true]]);

        Livewire::test(ContratoResource\Pages\EditContrato::class, ['record' => $plano->getKey()])
            ->fillForm(['name' => 'Renomeado', 'base_price' => 500, 'billing_cycle' => 'monthly'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertTrue($plano->fresh()->features[self::FEATURE], 'salvar o contrato não desliga o complemento');

        $novo = ContratoResource\Pages\CreateContrato::mergeGroupedFeatures(['features_group_complementos' => [self::FEATURE], 'features_group_comercial' => ['tabela_clients']]);
        $this->assertArrayNotHasKey(self::FEATURE, $novo, 'contrato novo não nasce com complemento');
    }

    public function test_central_screen_contracts_cancels_and_hides_the_early_termination_from_regular_users(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));
        $cliente = $this->tenant($this->plano());

        $tela = Livewire::test(ComplementosRelationManager::class, ['ownerRecord' => $cliente, 'pageClass' => EditTenant::class])->assertSuccessful()
            ->callTableAction('contratar', data: ['feature' => self::FEATURE, 'valor' => 150]);

        $c = TenantComplemento::withoutGlobalScopes()->where('tenant_id', $cliente->id)->firstOrFail();
        $this->assertEquals(150, $c->valor_cheio);
        $this->assertEquals(90, $c->valor_mensal, 'cobrado = cheio com 40% de desconto');
        $tela->assertCanSeeTableRecords([$c])->callTableAction('cancelar', $c)->assertNotified('Não foi possível');
        $this->assertSame('ativo', $c->fresh()->status);

        $tela->assertTableActionVisible('encerrarAntecipado', $c)->callTableAction('encerrarAntecipado', $c, data: ['motivo' => 'Acordo comercial']);
        $this->assertSame('encerrado', $c->fresh()->status);

        $this->assertTrue(class_exists(SincronizarComplementos::class));
    }
}
