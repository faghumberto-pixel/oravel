<?php

namespace Tests\Feature;

use App\Filament\Pages\NotasFiscaisConfig;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantFiscalSetting;
use App\Models\User;
use App\Services\Fiscal\FakeFiscalProvider;
use App\Services\Fiscal\FiscalException;
use App\Services\Fiscal\FiscalProvider;
use App\Services\Fiscal\FocusNfeProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 9A: cadastro fiscal, certificado A1 repassado ao emissor (nunca guardado) e provider Focus NFe. */
class NotasFiscaisConfigTest extends TestCase
{
    use DatabaseTransactions;

    private const DADOS = [
        'cnpj' => '11.222.333/0001-81', 'razao_social' => 'Locadora Teste Ltda', 'inscricao_municipal' => '12345', 'regime_tributario' => 'simples', 'cep' => '13480-000',
        'logradouro' => 'Rua A', 'numero' => '10', 'bairro' => 'Centro', 'municipio' => 'Limeira', 'uf' => 'sp', 'codigo_municipio' => '3526902', 'email' => 'fiscal@x.com.br',
        'item_lista_servico' => '07.02', 'aliquota_iss' => 3, 'descricao_padrao' => 'Locação de equipamentos', 'ambiente' => 'homologacao', 'padrao' => 'municipal',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        FakeFiscalProvider::reset();
        $this->app->bind(FiscalProvider::class, FakeFiscalProvider::class);
    }

    private function tenant(bool $complemento = true): Tenant
    {
        $plan = Plan::create(['name' => 'Plano '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [TenantFiscalSetting::FEATURE => $complemento]]);

        return Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function admin(Tenant $t): User
    {
        $u = User::create(['name' => 'Admin '.uniqid(), 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    public function test_page_is_gated_by_the_complement_and_by_admin(): void
    {
        $this->actingAs($this->admin($this->tenant(false)));
        $this->assertFalse(NotasFiscaisConfig::canAccess());

        $this->actingAs($this->admin($this->tenant(true)));
        $this->assertTrue(NotasFiscaisConfig::canAccess());
    }

    public function test_saves_fiscal_data_normalized_and_prefills_from_the_company(): void
    {
        $t = $this->tenant();
        $t->update(['cpf_cnpj' => '99.888.777/0001-66', 'cidade' => 'Campinas']);
        $this->actingAs($this->admin($t));

        Livewire::test(NotasFiscaisConfig::class)->assertSuccessful()->assertFormSet(['cnpj' => '99.888.777/0001-66', 'municipio' => 'Campinas'])
            ->fillForm(self::DADOS)->call('salvar')->assertHasNoFormErrors();

        $c = TenantFiscalSetting::daEmpresa($t->id);
        $this->assertSame('11222333000181', $c->cnpj);
        $this->assertSame('SP', $c->uf);
        $this->assertFalse($c->enabled);
        $this->assertSame([], $c->faltando());
    }

    public function test_certificate_goes_to_the_provider_and_is_never_stored(): void
    {
        Storage::fake('local');
        $t = $this->tenant();
        $this->actingAs($this->admin($t));

        $pfx = UploadedFile::fake()->createWithContent('cert.pfx', 'CONTEUDO-BINARIO-DO-CERTIFICADO');
        Livewire::test(NotasFiscaisConfig::class)->fillForm(self::DADOS)
            ->set('certificado', $pfx)->set('senhaCertificado', 'SENHA-SECRETA')->call('enviarCertificado')
            ->assertSet('senhaCertificado', '')->assertSet('certificado', null);

        $c = TenantFiscalSetting::daEmpresa($t->id);
        $this->assertNotNull($c->provider_company_id);
        $this->assertNotNull($c->token_homologacao);
        $this->assertNotNull($c->certificado_validade);
        $this->assertSame([['sincronizarEmpresa', ['com_certificado' => true]]], FakeFiscalProvider::$chamadas);

        $banco = json_encode(\DB::table('tenant_fiscal_settings')->where('tenant_id', $t->id)->first());
        $this->assertStringNotContainsString('SENHA-SECRETA', $banco);
        $this->assertStringNotContainsString(base64_encode('CONTEUDO-BINARIO-DO-CERTIFICADO'), $banco);
        $this->assertStringNotContainsString('tok-h-', $banco, 'o token do emissor fica criptografado');
    }

    public function test_provider_error_on_certificate_is_shown_and_nothing_is_turned_on(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin($t));
        FakeFiscalProvider::$falhar = 'Senha do certificado incorreta.';

        Livewire::test(NotasFiscaisConfig::class)->fillForm(self::DADOS)
            ->set('certificado', UploadedFile::fake()->createWithContent('c.pfx', 'X'))->set('senhaCertificado', 'errada')->call('enviarCertificado')
            ->assertSet('senhaCertificado', '');

        $c = TenantFiscalSetting::daEmpresa($t->id);
        $this->assertNull($c->provider_company_id);
        $this->assertFalse($c->enabled);
    }

    public function test_turns_on_only_with_complete_data_and_a_working_connection(): void
    {
        $t = $this->tenant();
        $this->actingAs($this->admin($t));

        // Cadastro incompleto: não liga.
        Livewire::test(NotasFiscaisConfig::class)->fillForm(['cnpj' => '11222333000181', 'ambiente' => 'homologacao', 'padrao' => 'municipal'])->call('testarEAtivar');
        $this->assertFalse((bool) TenantFiscalSetting::daEmpresa($t->id)?->enabled);

        // Completo, com a empresa já no emissor: liga e depois desliga.
        Livewire::test(NotasFiscaisConfig::class)->fillForm(self::DADOS)
            ->set('certificado', UploadedFile::fake()->createWithContent('c.pfx', 'X'))->set('senhaCertificado', 's')->call('enviarCertificado')
            ->call('testarEAtivar');
        $c = TenantFiscalSetting::daEmpresa($t->id);
        $this->assertTrue($c->enabled);
        $this->assertTrue($c->pronta());

        Livewire::test(NotasFiscaisConfig::class)->call('desligar');
        $this->assertFalse($c->fresh()->enabled);

        // Falha no emissor ao testar: fica desligada e guarda o erro.
        FakeFiscalProvider::$falhar = 'Emissor fora do ar.';
        Livewire::test(NotasFiscaisConfig::class)->call('testarEAtivar');
        $this->assertFalse($c->fresh()->enabled);
        $this->assertSame('Emissor fora do ar.', $c->fresh()->last_error);
    }

    public function test_settings_are_isolated_between_companies(): void
    {
        $a = $this->tenant();
        $b = $this->tenant();
        $this->actingAs($this->admin($a));
        Livewire::test(NotasFiscaisConfig::class)->fillForm(self::DADOS)->call('salvar');

        $this->actingAs($this->admin($b));
        $this->assertNull(TenantFiscalSetting::daEmpresa($b->id));
        $this->assertSame(0, TenantFiscalSetting::count(), 'o escopo de tenant esconde a configuração da outra empresa');
    }

    public function test_focus_provider_registers_company_with_main_token_and_basic_auth(): void
    {
        config(['services.focusnfe.token' => 'TOKEN-ORAVEL', 'services.focusnfe.urls.producao' => 'https://focus.test']);
        Http::fake(['focus.test/v2/empresas' => Http::response(['id' => 77, 'token_homologacao' => 'th', 'token_producao' => 'tp', 'certificado_valido_ate' => '2027-05-01'], 201)]);
        $c = new TenantFiscalSetting(self::DADOS + ['tenant_id' => 'x']);

        $r = (new FocusNfeProvider)->sincronizarEmpresa($c, base64_encode('PFX'), 'senha');

        $this->assertSame(['id' => '77', 'token_homologacao' => 'th', 'token_producao' => 'tp', 'certificado_validade' => '2027-05-01'], $r);
        Http::assertSent(function ($req) {
            $b = $req->data();

            return $req->method() === 'POST' && $req->header('Authorization')[0] === 'Basic '.base64_encode('TOKEN-ORAVEL:')
                && $b['cnpj'] === '11222333000181' && $b['regime_tributario'] === 1 && $b['habilita_nfse'] === true
                && $b['arquivo_certificado_base64'] === base64_encode('PFX') && $b['senha_certificado'] === 'senha';
        });
    }

    public function test_focus_provider_translates_errors_and_requires_the_main_token(): void
    {
        config(['services.focusnfe.token' => null]);
        $this->expectException(FiscalException::class);
        (new FocusNfeProvider)->sincronizarEmpresa(new TenantFiscalSetting(self::DADOS));
    }

    public function test_focus_provider_surfaces_the_emitter_message(): void
    {
        config(['services.focusnfe.token' => 'T', 'services.focusnfe.urls.producao' => 'https://focus.test']);
        Http::fake(['focus.test/v2/empresas' => Http::response(['codigo' => 'certificado_invalido', 'mensagem' => 'Certificado inválido ou senha incorreta.'], 422)]);

        try {
            (new FocusNfeProvider)->sincronizarEmpresa(new TenantFiscalSetting(self::DADOS), 'AAA', 'x');
            $this->fail('deveria lançar');
        } catch (FiscalException $e) {
            $this->assertSame('Certificado inválido ou senha incorreta.', $e->getMessage());
        }
    }
}
