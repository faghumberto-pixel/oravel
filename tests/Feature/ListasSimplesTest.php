<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Filament\Resources\ContractResource\Pages\ListContracts;
use App\Filament\Resources\MaintenanceOrderResource\Pages\ListMaintenanceOrders;
use App\Filament\Resources\PropostaComercialResource\Pages\ListPropostaComerciais;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Listas na interface simples: menos colunas e filtros; Locadora segue com a lista completa. */
class ListasSimplesTest extends TestCase
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

    public function test_lista_de_clientes_simples_mostra_so_o_essencial(): void
    {
        $this->actingAs($this->admin('servicos'));

        Livewire::test(ListClients::class)->assertSuccessful()
            ->assertTableColumnExists('name')->assertTableColumnExists('phone')->assertTableColumnExists('email')
            ->assertTableColumnHidden('state')->assertTableColumnHidden('whatsapp')->assertTableColumnHidden('activity_type');
    }

    public function test_listas_de_contratos_e_propostas_simples_escondem_o_que_e_de_locacao(): void
    {
        $this->actingAs($this->admin('servicos'));

        Livewire::test(ListContracts::class)->assertSuccessful()->assertTableColumnHidden('asset.patrimonio')->assertTableColumnExists('service_category');
        Livewire::test(ListPropostaComerciais::class)->assertSuccessful()->assertTableColumnHidden('items_count')->assertTableColumnExists('client.name');
    }

    public function test_locadora_continua_com_as_listas_completas(): void
    {
        $this->actingAs($this->admin('locadora'));

        Livewire::test(ListClients::class)->assertTableColumnVisible('state')->assertTableColumnVisible('activity_type');
        Livewire::test(ListContracts::class)->assertTableColumnVisible('asset.patrimonio');
        Livewire::test(ListPropostaComerciais::class)->assertTableColumnVisible('items_count');
    }

    public function test_listas_de_manutencao_simples_mostram_so_o_que_importa(): void
    {
        $this->actingAs($this->admin('manutencao'));

        Livewire::test(ListMaintenanceOrders::class)->assertSuccessful()->assertTableColumnExists('os_number')->assertTableColumnExists('status')
            ->assertTableColumnHidden('is_rework')->assertTableColumnHidden('asset.abcMatrix.nivel');
        Livewire::test(ListAssets::class)->assertSuccessful()->assertTableColumnExists('name')->assertTableColumnExists('status')
            ->assertTableColumnHidden('chassi')->assertTableColumnHidden('last_horimetro')->assertTableColumnHidden('placa');
    }

    public function test_locadora_continua_com_as_listas_de_manutencao_completas(): void
    {
        $this->actingAs($this->admin('locadora'));

        Livewire::test(ListMaintenanceOrders::class)->assertTableColumnVisible('is_rework');
        Livewire::test(ListAssets::class)->assertTableColumnVisible('chassi');
    }
}
