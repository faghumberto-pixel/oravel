<?php

namespace Tests\Feature;

use App\Filament\Resources\PropostaComercialResource\Pages\CreatePropostaComercial;
use App\Models\Client;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Propostas na interface simples: formulário curto de serviço; Locadora segue com a proposta completa. */
class PropostasSimplesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(string $pacote): User
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true, 'features' => array_fill_keys(Pacotes::features($pacote), true)]);
        $t = Tenant::factory()->create(['plan_id' => $plano->id]);
        $u = TenantProvisioner::provision($t, ['name' => 'Maria', 'email' => 'm-'.uniqid().'@oravel.test', 'password' => 'senha12345']);
        $u->forceFill(['is_approved' => true])->save();

        return $u;
    }

    public function test_proposta_simples_cria_rascunho_com_servicos_e_vendedor_automatico(): void
    {
        $u = $this->admin('servicos');
        $this->actingAs($u);
        $cliente = Client::create(['tenant_id' => $u->tenant_id, 'name' => 'Construtora Alfa']);

        Livewire::test(CreatePropostaComercial::class)
            ->assertFormFieldDoesNotExist('cabecalho')->assertFormFieldDoesNotExist('proposta_comercial_template_id')->assertFormFieldExists('terms')
            ->fillForm(['client_id' => $cliente->id, 'items' => [['description' => 'Manutenção mensal do galpão', 'quantity' => 1, 'unit_price' => 1500, 'unit_period' => 'mensal']]])
            ->call('create')->assertHasNoFormErrors();

        $p = PropostaComercial::where('client_id', $cliente->id)->firstOrFail();
        $this->assertSame($u->tenant_id, $p->tenant_id);
        $this->assertSame($u->id, $p->seller_user_id, 'vendedor é quem criou');
        $item = $p->items()->firstOrFail();
        $this->assertSame(PropostaComercialItem::TYPE_SERVICO, $item->type);
        $this->assertSame('Manutenção mensal do galpão', $item->description);
    }

    public function test_locadora_continua_com_a_proposta_completa(): void
    {
        $this->actingAs($this->admin('locadora'));

        Livewire::test(CreatePropostaComercial::class)->assertFormFieldExists('cabecalho')->assertFormFieldExists('proposta_comercial_template_id');
    }
}
