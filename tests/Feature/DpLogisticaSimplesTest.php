<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Filament\Resources\FleetDriverResource\Pages\CreateFleetDriver;
use App\Filament\Resources\FleetDriverResource\Pages\ListFleetDrivers;
use App\Filament\Resources\FleetVehicleResource\Pages\CreateFleetVehicle;
use App\Filament\Resources\FleetVehicleResource\Pages\ListFleetVehicles;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Departamento Pessoal e Logística na interface simples: menos campos e colunas; Locadora segue completa. */
class DpLogisticaSimplesTest extends TestCase
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

    public function test_colaborador_simples_esconde_funcao_cadastrada_horas_e_usuario(): void
    {
        $this->actingAs($this->admin('departamento_pessoal'));

        Livewire::test(CreateEmployee::class)->assertFormFieldExists('name')->assertFormFieldExists('cpf')->assertFormFieldExists('admission_date')
            ->assertFormFieldIsHidden('job_role_id')->assertFormFieldIsHidden('daily_work_hours')->assertFormFieldIsHidden('user_id');
        Livewire::test(ListEmployees::class)->assertSuccessful()->assertTableColumnHidden('jobRole.name')->assertTableColumnExists('name');
    }

    public function test_veiculo_e_motorista_simples_escondem_dados_tecnicos(): void
    {
        $this->actingAs($this->admin('logistica'));

        Livewire::test(CreateFleetVehicle::class)->assertFormFieldExists('placa')->assertFormFieldIsHidden('custo_por_km')->assertFormFieldIsHidden('tag_sem_parar');
        Livewire::test(ListFleetVehicles::class)->assertSuccessful()->assertTableColumnHidden('alertas')->assertTableColumnExists('placa');
        Livewire::test(CreateFleetDriver::class)->assertFormFieldExists('cnh_number')->assertFormFieldIsHidden('freight_carrier_id');
        Livewire::test(ListFleetDrivers::class)->assertSuccessful()->assertTableColumnHidden('alertas');
    }

    public function test_locadora_continua_completa(): void
    {
        $this->actingAs($this->admin('locadora'));

        Livewire::test(CreateEmployee::class)->assertFormFieldIsVisible('job_role_id');
        Livewire::test(CreateFleetVehicle::class)->assertFormFieldIsVisible('custo_por_km');
        Livewire::test(ListFleetVehicles::class)->assertTableColumnVisible('alertas');
    }
}
