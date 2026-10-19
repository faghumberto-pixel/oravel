<?php

namespace Tests\Feature;

use App\Filament\Resources\ContractResource\Pages\CreateContract;
use App\Filament\Resources\ContractResource\Pages\EditContract;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Contratos na interface simples: formulário curto de serviço, sem exclusão; Locadora segue com o contrato completo. */
class ContratosSimplesTest extends TestCase
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

    public function test_contrato_simples_pede_so_cliente_servico_valor_e_prazo(): void
    {
        $u = $this->admin('servicos');
        $this->actingAs($u);
        $cliente = Client::create(['tenant_id' => $u->tenant_id, 'name' => 'Construtora Alfa']);

        Livewire::test(CreateContract::class)
            ->assertFormFieldExists('service_category')->assertFormFieldDoesNotExist('local_tipo')->assertFormFieldDoesNotExist('multa_rescisoria')->assertFormFieldDoesNotExist('cnpj')
            ->fillForm(['client_id' => $cliente->id, 'contract_number' => 'CT-DEMO-1', 'service_category' => 'Manutenção mensal do galpão', 'price' => 1500, 'start_date' => '2026-11-01', 'status' => 'Ativo'])
            ->call('create')->assertHasNoFormErrors();

        $c = Contract::where('contract_number', 'CT-DEMO-1')->firstOrFail();
        $this->assertSame($u->tenant_id, $c->tenant_id);
        $this->assertSame('Manutenção mensal do galpão', $c->service_category);
        $this->assertSame(Contract::BILLING_MENSAL_FIXO, $c->billing_type);
        $this->assertNull($c->asset_id, 'item atendido é opcional');
    }

    public function test_contrato_gerado_de_proposta_abre_e_edita_na_tela_simples(): void
    {
        $u = $this->admin('servicos');
        $this->actingAs($u);
        $cliente = Client::create(['tenant_id' => $u->tenant_id, 'name' => 'Cliente Proposta']);
        $c = Contract::create(['tenant_id' => $u->tenant_id, 'client_id' => $cliente->id, 'service_category' => 'Operador', 'contract_number' => 'SV-X', 'start_date' => now(), 'price' => 900, 'billing_type' => Contract::BILLING_MENSAL_FIXO, 'status' => 'Draft']);

        Livewire::test(EditContract::class, ['record' => $c->getKey()])->assertFormSet(['service_category' => 'Operador'])
            ->fillForm(['status' => 'Ativo'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Ativo', $c->fresh()->status);
    }

    public function test_locadora_continua_com_o_contrato_completo(): void
    {
        $this->actingAs($this->admin('locadora'));

        Livewire::test(CreateContract::class)->assertFormFieldExists('local_tipo')->assertFormFieldExists('multa_rescisoria');
    }
}
