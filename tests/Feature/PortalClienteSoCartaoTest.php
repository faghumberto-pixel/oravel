<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\Auth\Login as ClientLogin;
use App\Models\Client;
use App\Models\Tenant;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Portal do Cliente incluído, sem custo, só para quem paga no cartão recorrente. */
class PortalClienteSoCartaoTest extends TestCase
{
    use DatabaseTransactions;

    private function cliente(?string $forma): Client
    {
        $tenant = Tenant::factory()->create(['payment_method' => $forma]);

        return Client::factory()->create(['tenant_id' => $tenant->id, 'email' => 'cli-'.uniqid().'@exemplo.com', 'password' => 'senha12345', 'portal_access_enabled_at' => now()]);
    }

    public function test_cartao_e_empresa_antiga_sem_forma_gravada_tem_portal_e_boleto_pix_nao(): void
    {
        $this->assertTrue(Tenant::factory()->create(['payment_method' => 'cartao'])->portalClienteIncluido());
        $this->assertTrue(Tenant::factory()->create(['payment_method' => null])->portalClienteIncluido(), 'empresa antiga não perde o portal');
        $this->assertFalse(Tenant::factory()->create(['payment_method' => 'boleto_pix'])->portalClienteIncluido());
    }

    public function test_cliente_so_acessa_o_painel_do_portal_se_a_empresa_inclui(): void
    {
        $painel = Filament::getPanel('portal-cliente');

        $this->assertTrue($this->cliente('cartao')->canAccessPanel($painel));
        $this->assertFalse($this->cliente('boleto_pix')->canAccessPanel($painel));
    }

    public function test_login_do_portal_explica_o_motivo_so_para_quem_acertou_a_senha(): void
    {
        $semPortal = $this->cliente('boleto_pix');

        $certa = Livewire::test(ClientLogin::class)->set('data.email', $semPortal->email)->set('data.password', 'senha12345')->call('authenticate');
        $this->assertStringContainsString('não está disponível', implode(' ', $certa->errors()->get('data.email')));

        // senha errada: segue a mensagem genérica de credenciais, sem revelar nada
        $errada = Livewire::test(ClientLogin::class)->set('data.email', $semPortal->email)->set('data.password', 'errada-errada')->call('authenticate');
        $this->assertStringNotContainsString('não está disponível', implode(' ', $errada->errors()->get('data.email')));
    }

    public function test_checkout_mostra_o_portal_so_na_opcao_do_cartao(): void
    {
        config(['services.asaas.api_key' => 'KEY']);
        $p = Pacotes::planoDeVitrine('servicos');
        $p->update(['base_price' => 300, 'price' => 300, 'is_active' => true]);

        $this->get(route('checkout.pacote', 'servicos'))->assertOk()
            ->assertSee('Inclui o Portal do Cliente, sem custo extra')->assertSee('Não inclui o Portal do Cliente');
    }
}
