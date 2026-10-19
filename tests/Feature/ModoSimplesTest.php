<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceActionTypeResource\Pages\CreateMaintenanceActionType;
use App\Filament\Resources\MaintenanceActionTypeResource\Pages\ListMaintenanceActionTypes;
use App\Filament\Resources\MaintenanceOrderResource\Pages\CreateMaintenanceOrder;
use App\Models\Asset;
use App\Models\MaintenanceActionType;
use App\Models\MaintenanceOrder;
use App\Models\ReportedProblem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Interface simples: formulário curto da OS, "o que você mantém" e cadastros que a empresa edita (sem exclusão). */
class ModoSimplesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(string $pacote, ?bool $interfaceSimples = null): User
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true, 'features' => array_fill_keys(Pacotes::features($pacote), true)]);
        $t = Tenant::factory()->create(['plan_id' => $plano->id, 'interface_simples' => $interfaceSimples]);
        $u = TenantProvisioner::provision($t, ['name' => 'Maria', 'email' => 'm-'.uniqid().'@oravel.test', 'password' => 'senha12345']);
        $u->forceFill(['is_approved' => true])->save();

        return $u;
    }

    public function test_interface_simples_e_o_padrao_de_quem_nao_contratou_locadora_e_a_central_pode_forcar(): void
    {
        $this->assertTrue($this->admin('manutencao')->tenant->interfaceSimples());
        $this->assertTrue($this->admin('servicos')->tenant->interfaceSimples());
        $this->assertFalse($this->admin('locadora')->tenant->interfaceSimples());
        $this->assertFalse($this->admin('manutencao', false)->tenant->interfaceSimples(), 'Central força a completa');
        $this->assertTrue($this->admin('locadora', true)->tenant->interfaceSimples(), 'Central força a simples');
    }

    public function test_so_empresas_em_interface_simples_ganham_o_catalogo_de_exemplo(): void
    {
        $simples = $this->admin('manutencao')->tenant;
        $completa = $this->admin('locadora')->tenant;

        $this->assertTrue(MaintenanceActionType::withoutGlobalScopes()->where('tenant_id', $simples->id)->where('name', 'Troca de óleo')->exists());
        $this->assertTrue(ReportedProblem::withoutGlobalScopes()->where('tenant_id', $simples->id)->where('description', 'Vazamento')->exists());
        $this->assertSame(0, MaintenanceActionType::withoutGlobalScopes()->where('tenant_id', $completa->id)->count());
    }

    public function test_os_simples_cria_com_poucos_campos_e_marca_o_que_foi_feito(): void
    {
        $u = $this->admin('manutencao');
        $this->actingAs($u);
        $carro = Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Carro do João', 'status' => Asset::STATUS_DISPONIVEL]);
        $acao = MaintenanceActionType::where('name', 'Troca de óleo')->firstOrFail();

        Livewire::test(CreateMaintenanceOrder::class)
            ->assertFormFieldDoesNotExist('horimetro_entry')->assertFormFieldDoesNotExist('is_prazo_fatal')
            ->fillForm(['asset_id' => $carro->id, 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'description' => 'Revisão dos 10 mil km', 'actionTypes' => [$acao->id], 'status' => 'Aberto'])
            ->call('create')->assertHasNoFormErrors();

        $os = MaintenanceOrder::where('asset_id', $carro->id)->firstOrFail();
        $this->assertSame(MaintenanceOrder::TYPE_PREVENTIVE, $os->maintenance_type);
        $this->assertSame([$acao->id], $os->actionTypes()->pluck('maintenance_action_types.id')->all());
        $this->assertSame($u->tenant_id, $os->tenant_id);
        $this->assertNotEmpty($os->os_number);
    }

    public function test_locadora_continua_com_o_formulario_completo(): void
    {
        $this->actingAs($this->admin('locadora'));

        Livewire::test(CreateMaintenanceOrder::class)->assertFormFieldExists('horimetro_entry')->assertFormFieldExists('is_prazo_fatal');
    }

    public function test_empresa_cadastra_tipos_de_acao_sem_nome_repetido_e_nunca_exclui(): void
    {
        $u = $this->admin('manutencao');
        $this->actingAs($u);

        Livewire::test(CreateMaintenanceActionType::class)->fillForm(['name' => 'Lavagem completa', 'is_active' => true])->call('create')->assertHasNoFormErrors();
        $tipo = MaintenanceActionType::where('name', 'Lavagem completa')->firstOrFail();

        Livewire::test(CreateMaintenanceActionType::class)->fillForm(['name' => 'Lavagem completa'])->call('create')->assertHasFormErrors(['name']);

        $this->assertTrue($u->can('update', $tipo));
        $this->assertFalse($u->can('delete', $tipo), 'tipos de ação nunca são excluídos, só desativados');

        Livewire::test(ListMaintenanceActionTypes::class)->callTableAction('alternar', $tipo);
        $this->assertFalse($tipo->fresh()->is_active);
    }

    public function test_cada_empresa_ve_so_os_proprios_tipos_de_acao(): void
    {
        $a = $this->admin('manutencao');
        $b = $this->admin('manutencao');
        MaintenanceActionType::withoutGlobalScopes()->create(['tenant_id' => $b->tenant_id, 'name' => 'Ação só da empresa B']);
        $this->actingAs($a);

        $this->assertSame(0, MaintenanceActionType::where('name', 'Ação só da empresa B')->count());
    }
}
