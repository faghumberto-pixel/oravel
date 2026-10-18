<?php

namespace Tests\Feature;

use App\Filament\Resources\AccountReceivableResource\Pages\ManageAccountReceivables;
use App\Filament\Resources\NotaFiscalResource;
use App\Filament\Resources\NotaFiscalResource\Pages\ManageNotasFiscais;
use App\Mail\NotaFiscalMail;
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
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 9C: lista de notas, cancelamento, envio ao cliente, certificado e coluna na conta a receber. */
class NotasFiscaisPainelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        FakeFiscalProvider::reset();
        $this->app->bind(FiscalProvider::class, FakeFiscalProvider::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
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

    private function nota(Tenant $t, string $status = NotaFiscal::AUTORIZADA, array $extra = [], array $cliente = []): NotaFiscal
    {
        $c = Client::create($cliente + ['tenant_id' => $t->id, 'name' => 'Cliente '.uniqid(), 'cpf_cnpj' => '11.444.777/0001-61', 'email' => 'cliente@x.com']);
        $conta = AccountReceivable::create(['tenant_id' => $t->id, 'client_id' => $c->id, 'description' => 'Locação', 'amount' => 1000, 'due_date' => now()->addDays(5), 'status' => 'pendente']);

        return NotaFiscal::withoutGlobalScopes()->create($extra + [
            'tenant_id' => $t->id, 'account_receivable_id' => $conta->id, 'client_id' => $c->id, 'ref' => 'ref-'.uniqid(), 'ambiente' => 'homologacao', 'status' => $status, 'valor' => 1000,
            'tomador' => ['nome' => $c->name, 'email' => 'cliente@x.com', 'documento' => '11444777000161', 'tipo' => 'cnpj'], 'numero' => '1234', 'codigo_verificacao' => 'ABC123',
            'pdf_url' => $status === NotaFiscal::AUTORIZADA ? 'https://fake.test/n.pdf' : null, 'xml_url' => $status === NotaFiscal::AUTORIZADA ? 'https://fake.test/n.xml' : null, 'emitida_em' => now(),
        ]);
    }

    public function test_list_shows_only_the_company_notes_and_needs_the_complement(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $minha = $this->nota($a);
        $alheia = $this->nota($b);

        $this->actingAs($this->admin($a));
        Livewire::test(ManageNotasFiscais::class)->assertSuccessful()->assertCanSeeTableRecords([$minha])->assertCanNotSeeTableRecords([$alheia]);

        $semModulo = $this->tenant(false);
        $this->actingAs($this->admin($semModulo));
        $this->assertFalse(NotaFiscalResource::canViewAny());
    }

    public function test_the_complement_never_comes_inside_a_package(): void
    {
        foreach (array_keys(Pacotes::opcoes()) as $codigo) {
            $this->assertNotContains(TenantFiscalSetting::FEATURE, Pacotes::features($codigo), $codigo);
        }
    }

    public function test_cancel_needs_authorized_note_and_a_real_reason_and_goes_to_the_emitter(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin($t));
        $nota = $this->nota($t);

        Livewire::test(ManageNotasFiscais::class)
            ->callTableAction('cancelar', $nota, ['motivo' => 'curto'])->assertHasTableActionErrors(['motivo']);
        $this->assertSame(NotaFiscal::AUTORIZADA, $nota->fresh()->status);

        Livewire::test(ManageNotasFiscais::class)->callTableAction('cancelar', $nota, ['motivo' => 'Serviço cancelado a pedido do cliente'])->assertNotified('Nota cancelada');

        $nota = $nota->fresh();
        $this->assertSame(NotaFiscal::CANCELADA, $nota->status);
        $this->assertSame('Serviço cancelado a pedido do cliente', $nota->cancelamento_motivo);
        $this->assertNotNull($nota->cancelada_em);
        $this->assertSame('cancelarNfse', FakeFiscalProvider::$chamadas[0][0]);

        $processando = $this->nota($t, NotaFiscal::PROCESSANDO);
        Livewire::test(ManageNotasFiscais::class)->assertTableActionHidden('cancelar', $processando);
    }

    public function test_cancel_refused_by_the_city_keeps_the_note_authorized(): void
    {
        $t = $this->tenant();
        $nota = $this->nota($t);
        $this->app->bind(FiscalProvider::class, fn () => new class extends FakeFiscalProvider
        {
            public function cancelarNfse($c, string $ref, string $motivo): array
            {
                return ['status' => 'autorizada', 'mensagem' => 'Prazo de cancelamento expirado.'];
            }
        });

        try {
            app(NotaFiscalService::class)->cancelar($nota, 'Cancelamento fora do prazo da prefeitura');
            $this->fail('deveria lançar');
        } catch (FiscalException $e) {
            $this->assertSame('Prazo de cancelamento expirado.', $e->getMessage());
        }

        $this->assertSame(NotaFiscal::AUTORIZADA, $nota->fresh()->status);
    }

    public function test_send_by_email_goes_to_the_client_with_the_links(): void
    {
        Mail::fake();
        $t = $this->tenant();
        $nota = $this->nota($t);

        app(NotaFiscalService::class)->enviarPorEmail($nota);

        Mail::assertSent(NotaFiscalMail::class, fn (NotaFiscalMail $m) => $m->hasTo('cliente@x.com') && $m->nota->is($nota) && str_contains($m->render(), 'https://fake.test/n.pdf'));
        $this->assertNotNull($nota->fresh()->enviada_em);

        $this->assertStringContainsString('ainda não foi autorizada', $this->erro(fn () => app(NotaFiscalService::class)->enviarPorEmail($this->nota($t, NotaFiscal::PROCESSANDO))));

        $semEmail = $this->nota($t, NotaFiscal::AUTORIZADA, ['tomador' => ['nome' => 'X', 'email' => 'invalido']], ['email' => null]);
        $this->assertStringContainsString('e-mail válido', $this->erro(fn () => app(NotaFiscalService::class)->enviarPorEmail($semEmail)));
    }

    public function test_send_by_whatsapp_explains_what_is_missing(): void
    {
        $t = $this->tenant();
        $nota = $this->nota($t);

        $this->assertStringContainsString('WhatsApp da empresa não está ligado', $this->erro(fn () => app(NotaFiscalService::class)->enviarPorWhatsApp($nota)));
        $this->assertNull($nota->fresh()->enviada_em);
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

    public function test_email_goes_out_by_itself_on_authorization_only_when_the_company_asked(): void
    {
        Mail::fake();
        $ligada = $this->tenant(true, ['enviar_email_ao_autorizar' => true]);
        $desligada = $this->tenant();
        $a = $this->nota($ligada, NotaFiscal::PROCESSANDO);
        $b = $this->nota($desligada, NotaFiscal::PROCESSANDO);
        FakeFiscalProvider::$status = 'autorizada';

        app(NotaFiscalService::class)->reconsultar($a);
        app(NotaFiscalService::class)->reconsultar($b);

        Mail::assertSent(NotaFiscalMail::class, 1);
        Mail::assertSent(NotaFiscalMail::class, fn (NotaFiscalMail $m) => $m->nota->is($a));

        // Nova consulta de uma nota que já estava autorizada não reenvia.
        app(NotaFiscalService::class)->reconsultar($a->fresh());
        Mail::assertSent(NotaFiscalMail::class, 1);
    }

    public function test_expired_certificate_blocks_new_notes_and_warns_admins_before_it_expires(): void
    {
        $vencido = $this->tenant(true, ['certificado_validade' => today()->subDay()]);
        $c = Client::create(['tenant_id' => $vencido->id, 'name' => 'C', 'cpf_cnpj' => '11.444.777/0001-61']);
        $conta = AccountReceivable::create(['tenant_id' => $vencido->id, 'client_id' => $c->id, 'description' => 'x', 'amount' => 10, 'due_date' => now(), 'status' => 'pendente']);
        $this->assertStringContainsString('certificado digital da empresa venceu', $this->erro(fn () => app(NotaFiscalService::class)->emitir($conta)));

        $perto = $this->tenant(true, ['certificado_validade' => today()->addDays(7)]);
        $longe = $this->tenant(true, ['certificado_validade' => today()->addDays(200)]);
        $adminPerto = $this->admin($perto);
        $adminLonge = $this->admin($longe);

        $this->artisan('fiscal:avisar-certificados')->assertSuccessful();

        $this->assertSame(1, $adminPerto->notifications()->count());
        $this->assertSame(0, $adminLonge->notifications()->count());
    }

    public function test_account_receivable_table_shows_the_note_status(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin($t));
        $nota = $this->nota($t);

        Livewire::test(ManageAccountReceivables::class)
            ->assertCanSeeTableRecords([$nota->contaAReceber])
            ->assertTableColumnStateSet('nfse', 'autorizada', $nota->contaAReceber);
    }
}
