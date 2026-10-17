<?php

namespace Tests\Feature;

use App\Filament\Resources\AccountReceivableResource\Pages\ManageAccountReceivables;
use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\NotaFiscal;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantFiscalSetting;
use App\Models\User;
use App\Services\Fiscal\FakeFiscalProvider;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\FiscalProvider;
use App\Services\Fiscal\NotaFiscalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 9B: emissão da NFS-e a partir da conta a receber, retorno assíncrono, webhook e conferência. */
class NotasFiscaisEmissaoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        FakeFiscalProvider::reset();
        $this->app->bind(FiscalProvider::class, FakeFiscalProvider::class);
    }

    private function tenant(bool $complemento = true, array $config = []): Tenant
    {
        $plan = Plan::create(['name' => 'Plano '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [TenantFiscalSetting::FEATURE => $complemento, 'tabela_account_receivables' => true, 'tabela_clients' => true]]);
        $t = Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);

        TenantFiscalSetting::withoutGlobalScopes()->create($config + [
            'tenant_id' => $t->id, 'enabled' => true, 'ambiente' => 'homologacao', 'padrao' => 'municipal', 'cnpj' => '11222333000181', 'razao_social' => 'Locadora Teste',
            'inscricao_municipal' => '123', 'regime_tributario' => 'simples', 'codigo_municipio' => '3526902', 'cep' => '13480000', 'logradouro' => 'Rua A', 'numero' => '1',
            'bairro' => 'Centro', 'municipio' => 'Limeira', 'uf' => 'SP', 'email' => 'f@x.com', 'item_lista_servico' => '07.02', 'aliquota_iss' => 3,
            'descricao_padrao' => 'Locação de equipamentos', 'provider_company_id' => 'emp_1', 'token_homologacao' => 'tok-h', 'token_producao' => 'tok-p',
        ]);

        return $t;
    }

    private function admin(Tenant $t): User
    {
        $u = User::create(['name' => 'Admin '.uniqid(), 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    private function conta(Tenant $t, array $extra = [], array $cliente = []): AccountReceivable
    {
        $c = Client::create($cliente + ['tenant_id' => $t->id, 'name' => 'Cliente '.uniqid(), 'cpf_cnpj' => '11.444.777/0001-61', 'email' => 'c@x.com', 'city' => 'Campinas', 'uf' => 'SP']);

        return AccountReceivable::create($extra + ['tenant_id' => $t->id, 'client_id' => $c->id, 'description' => 'Locação out/26', 'amount' => 1500, 'due_date' => now()->addDays(10), 'status' => 'pendente']);
    }

    private function servico(): NotaFiscalService
    {
        return app(NotaFiscalService::class);
    }

    public function test_emits_in_processing_then_authorizes_on_reconsult(): void
    {
        $t = $this->tenant();
        $conta = $this->conta($t);

        $nota = $this->servico()->emitir($conta);

        $this->assertSame(NotaFiscal::PROCESSANDO, $nota->status);
        $this->assertSame('1500.00', $nota->valor);
        $this->assertStringContainsString('Locação de equipamentos', $nota->discriminacao);
        [$metodo, $dados] = FakeFiscalProvider::$chamadas[0];
        $this->assertSame('emitirNfse', $metodo);
        $this->assertSame('11222333000181', $dados['prestador']['cnpj']);
        $this->assertSame('11444777000161', $dados['tomador']['cnpj']);
        $this->assertSame(1500.0, $dados['servico']['valor_servicos']);

        FakeFiscalProvider::$status = 'autorizada';
        $nota = $this->servico()->reconsultar($nota);

        $this->assertSame(NotaFiscal::AUTORIZADA, $nota->status);
        $this->assertNotNull($nota->numero);
        $this->assertNotNull($nota->pdf_url);
        $this->assertNotNull($nota->emitida_em);
    }

    public function test_one_live_note_per_account_and_a_new_try_after_refusal(): void
    {
        $t = $this->tenant();
        $conta = $this->conta($t);

        FakeFiscalProvider::$recusar = 'Inscrição municipal inválida.';
        $recusada = $this->servico()->emitir($conta);
        $this->assertSame(NotaFiscal::ERRO, $recusada->status);
        $this->assertSame('Inscrição municipal inválida.', $recusada->erro);

        FakeFiscalProvider::$recusar = null;
        $nova = $this->servico()->emitir($conta);
        $this->assertNotSame($recusada->ref, $nova->ref, 'nova tentativa, nova referência no emissor');

        try {
            $this->servico()->emitir($conta);
            $this->fail('segunda nota viva deveria ser barrada');
        } catch (FiscalException $e) {
            $this->assertStringContainsString('já tem uma NFS-e', $e->getMessage());
        }
        $this->assertSame(2, NotaFiscal::withoutGlobalScopes()->where('account_receivable_id', $conta->id)->count());
    }

    public function test_validations_come_back_in_plain_portuguese(): void
    {
        $semModulo = $this->tenant(false);
        $this->assertStringContainsString('não estão ligadas', $this->erro(fn () => $this->servico()->emitir($this->conta($semModulo))));

        $t = $this->tenant();
        $this->assertStringContainsString('maior que zero', $this->erro(fn () => $this->servico()->emitir($this->conta($t, ['amount' => 0]))));
        $this->assertStringContainsString('CPF ou CNPJ', $this->erro(fn () => $this->servico()->emitir($this->conta($t, [], ['cpf_cnpj' => '123']))));
        $this->assertSame(0, NotaFiscal::withoutGlobalScopes()->where('tenant_id', $t->id)->count(), 'validação barra antes de criar a nota');

        $incompleto = $this->tenant(true, ['descricao_padrao' => null]);
        $this->assertStringContainsString('não estão ligadas', $this->erro(fn () => $this->servico()->emitir($this->conta($incompleto))));
    }

    private function erro(\Closure $acao): string
    {
        try {
            $acao();
        } catch (FiscalException $e) {
            return $e->getMessage();
        }

        $this->fail('deveria lançar FiscalException');
    }

    public function test_lost_connection_keeps_the_note_processing_and_reuses_the_same_reference(): void
    {
        $t = $this->tenant();
        $conta = $this->conta($t);
        $this->app->bind(FiscalProvider::class, fn () => new class extends FakeFiscalProvider
        {
            public function emitirNfse($c, string $ref, array $dados): array
            {
                throw new ConnectionException('timeout');
            }
        });

        $nota = app(NotaFiscalService::class)->emitir($conta);

        $this->assertSame(NotaFiscal::PROCESSANDO, $nota->status);
        $this->assertStringContainsString('conferindo', $nota->erro);

        $this->app->bind(FiscalProvider::class, FakeFiscalProvider::class);
        FakeFiscalProvider::$status = 'autorizada';
        $this->assertSame(NotaFiscal::AUTORIZADA, app(NotaFiscalService::class)->reconsultar($nota->fresh())->status);
        $this->assertSame($nota->ref, FakeFiscalProvider::$chamadas[0][1]['ref'], 'a conferência usa a mesma referência, sem duplicar');
    }

    public function test_webhook_checks_token_isolates_companies_and_is_idempotent(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $nota = $this->servico()->emitir($this->conta($a));
        FakeFiscalProvider::reset();
        FakeFiscalProvider::$status = 'autorizada';
        $tokenA = TenantFiscalSetting::daEmpresa($a->id)->webhook_token;

        $this->postJson("/api/webhooks/fiscal/{$a->id}", ['ref' => $nota->ref, 'status' => 'autorizado'], ['Authorization' => 'errado'])->assertForbidden();
        $this->postJson("/api/webhooks/fiscal/{$b->id}", ['ref' => $nota->ref, 'status' => 'autorizado'], ['Authorization' => $tokenA])->assertForbidden();
        $this->assertSame(NotaFiscal::PROCESSANDO, $nota->fresh()->status);

        $this->postJson("/api/webhooks/fiscal/{$a->id}", ['ref' => $nota->ref, 'status' => 'autorizado'], ['Authorization' => "Bearer {$tokenA}"])->assertOk();
        $this->assertSame(NotaFiscal::AUTORIZADA, $nota->fresh()->status);

        $this->postJson("/api/webhooks/fiscal/{$a->id}", ['ref' => $nota->ref, 'status' => 'autorizado'], ['Authorization' => $tokenA])->assertOk()->assertJson(['ignorado' => 'repetido']);
        $this->assertCount(1, FakeFiscalProvider::$chamadas, 'o aviso repetido não consulta o emissor de novo');

        // Referência de outra empresa nunca é tocada.
        FakeFiscalProvider::$status = 'processando';
        $notaB = $this->servico()->emitir($this->conta($b));
        FakeFiscalProvider::$status = 'autorizada';
        $this->postJson("/api/webhooks/fiscal/{$a->id}", ['ref' => $notaB->ref, 'status' => 'autorizado'], ['Authorization' => $tokenA])->assertJson(['ignorado' => 'nota_desconhecida']);
        $this->assertSame(NotaFiscal::PROCESSANDO, $notaB->fresh()->status);
    }

    public function test_webhook_failure_to_check_asks_the_emitter_to_retry(): void
    {
        $t = $this->tenant();
        $nota = $this->servico()->emitir($this->conta($t));
        $token = TenantFiscalSetting::daEmpresa($t->id)->webhook_token;
        FakeFiscalProvider::$falhar = 'Emissor fora do ar.';

        $this->postJson("/api/webhooks/fiscal/{$t->id}?token={$token}", ['ref' => $nota->ref, 'status' => 'autorizado'])->assertStatus(503);

        FakeFiscalProvider::$falhar = null;
        FakeFiscalProvider::$status = 'autorizada';
        $this->postJson("/api/webhooks/fiscal/{$t->id}?token={$token}", ['ref' => $nota->ref, 'status' => 'autorizado'])->assertOk();
        $this->assertSame(NotaFiscal::AUTORIZADA, $nota->fresh()->status);
    }

    public function test_reconsult_command_checks_only_old_processing_notes(): void
    {
        $t = $this->tenant();
        $velha = $this->servico()->emitir($this->conta($t));
        $nova = $this->servico()->emitir($this->conta($t));
        NotaFiscal::withoutGlobalScopes()->whereKey($velha->id)->update(['updated_at' => now()->subMinutes(30)]);
        FakeFiscalProvider::$status = 'autorizada';

        $this->artisan('fiscal:reconsultar')->assertSuccessful();

        $this->assertSame(NotaFiscal::AUTORIZADA, $velha->fresh()->status);
        $this->assertSame(NotaFiscal::PROCESSANDO, $nova->fresh()->status, 'a nota recente ainda espera o retorno');
    }

    public function test_automatic_emission_on_payment_only_when_the_company_turned_it_on(): void
    {
        $ligada = $this->tenant(true, ['emissao_automatica' => true]);
        $desligada = $this->tenant();
        $a = $this->conta($ligada);
        $b = $this->conta($desligada);

        $a->update(['status' => 'pago', 'payment_date' => now()->toDateString()]);
        $b->update(['status' => 'pago', 'payment_date' => now()->toDateString()]);

        $this->assertSame(1, NotaFiscal::withoutGlobalScopes()->where('account_receivable_id', $a->id)->count());
        $this->assertSame(0, NotaFiscal::withoutGlobalScopes()->where('account_receivable_id', $b->id)->count());

        // Pagamento repetido (webhook reenviado) não gera segunda nota.
        $a->update(['status' => 'pendente']);
        $a->update(['status' => 'pago']);
        $this->assertSame(1, NotaFiscal::withoutGlobalScopes()->where('account_receivable_id', $a->id)->count());
    }

    public function test_automatic_emission_failure_never_breaks_the_payment(): void
    {
        $t = $this->tenant(true, ['emissao_automatica' => true]);
        $conta = $this->conta($t, [], ['cpf_cnpj' => null]);

        $conta->update(['status' => 'pago']);

        $this->assertSame('pago', $conta->fresh()->status);
        $this->assertSame(0, NotaFiscal::withoutGlobalScopes()->where('account_receivable_id', $conta->id)->count());
    }

    public function test_table_action_emits_and_is_hidden_when_not_ready_or_already_issued(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin($t));
        $conta = $this->conta($t);

        Livewire::test(ManageAccountReceivables::class)
            ->assertTableActionVisible('emitirNfse', $conta)
            ->callTableAction('emitirNfse', $conta)
            ->assertNotified('NFS-e enviada para emissão')
            ->assertTableActionHidden('emitirNfse', $conta)
            ->assertTableActionVisible('atualizarNfse', $conta);

        $this->assertSame(1, NotaFiscal::withoutGlobalScopes()->where('account_receivable_id', $conta->id)->count());

        $semModulo = $this->tenant(false);
        $this->actingAs($this->admin($semModulo));
        Livewire::test(ManageAccountReceivables::class)->assertTableActionHidden('emitirNfse', $this->conta($semModulo));
    }

    public function test_notes_are_scoped_to_the_company(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $this->servico()->emitir($this->conta($a));

        $this->actingAs($this->admin($b));
        $this->assertSame(0, NotaFiscal::count());

        $this->actingAs($this->admin($a));
        $this->assertSame(1, NotaFiscal::count());
    }
}
