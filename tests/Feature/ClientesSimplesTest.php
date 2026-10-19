<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Cadastro de clientes na interface simples: ficha curta e sem exclusão; Locadora segue com a ficha completa. */
class ClientesSimplesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(string $pacote, ?string $forma = 'cartao'): User
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true, 'features' => array_fill_keys(Pacotes::features($pacote), true)]);
        $t = Tenant::factory()->create(['plan_id' => $plano->id, 'payment_method' => $forma]);
        $u = TenantProvisioner::provision($t, ['name' => 'Maria', 'email' => 'm-'.uniqid().'@oravel.test', 'password' => 'senha12345']);
        $u->forceFill(['is_approved' => true])->save();

        return $u;
    }

    public function test_ficha_simples_tem_so_o_basico_e_grava_nas_mesmas_colunas(): void
    {
        $u = $this->admin('servicos');
        $this->actingAs($u);

        Livewire::test(CreateClient::class)
            ->assertFormFieldExists('name')->assertFormFieldExists('document')->assertFormFieldExists('phone')->assertFormFieldExists('email')
            ->assertFormFieldDoesNotExist('state_registration')->assertFormFieldDoesNotExist('tax_regime')->assertFormFieldDoesNotExist('delivery_address')
            ->fillForm(['name' => 'João da Silva', 'document' => '123.456.789-09', 'phone' => '(19) 99999-9999', 'email' => 'joao@exemplo.com', 'city' => 'Campinas', 'uf' => 'SP'])
            ->call('create')->assertHasNoFormErrors();

        $c = Client::where('name', 'João da Silva')->firstOrFail();
        $this->assertSame($u->tenant_id, $c->tenant_id);
        $this->assertSame('Campinas', $c->city);
        $this->assertSame('joao@exemplo.com', $c->email);
    }

    public function test_locadora_continua_com_a_ficha_completa(): void
    {
        $this->actingAs($this->admin('locadora'));

        Livewire::test(CreateClient::class)->assertFormFieldExists('state_registration')->assertFormFieldExists('tax_regime');
    }

    public function test_acesso_ao_portal_so_aparece_para_quem_tem_o_portal_incluido(): void
    {
        $this->actingAs($this->admin('servicos', 'cartao'));
        Livewire::test(CreateClient::class)->assertFormFieldIsVisible('portal_access_enabled_at');

        $this->actingAs($this->admin('servicos', 'boleto_pix'));
        Livewire::test(CreateClient::class)->assertFormFieldIsHidden('portal_access_enabled_at');
    }

    public function test_nome_repetido_na_mesma_empresa_continua_barrado(): void
    {
        $u = $this->admin('servicos');
        $this->actingAs($u);
        Client::create(['tenant_id' => $u->tenant_id, 'name' => 'Cliente Repetido']);

        Livewire::test(CreateClient::class)->fillForm(['name' => 'Cliente Repetido'])->call('create')->assertHasFormErrors(['name']);
    }
}
