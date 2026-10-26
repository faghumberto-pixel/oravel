<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Central\Resources\TenantResource\Pages\EditTenant;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A tela da empresa (Central) edita os módulos do PRÓPRIO contrato: o que se muda
 * lá é o que aparece em Contratos, e o contrário. Complementos não são tocados.
 */
class TenantModulosDoContratoTest extends TestCase
{
    use DatabaseTransactions;

    private function superAdmin(): void
    {
        $this->actingAs(User::create([
            'name' => 'Super', 'email' => 'humberto@oravel.com.br',
            'password' => 'x', 'role' => 'admin', 'hourly_rate' => 0,
        ]));
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    private function empresa(Plan $plan, string $nome): Tenant
    {
        return Tenant::create(['name' => $nome, 'slug' => 'm-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function campo(string $feature): array
    {
        foreach (ContratoResource::groupedFeatureOptions() as $grupo => $options) {
            if (isset($options[$feature])) {
                return [ContratoResource::groupFieldName($grupo), $feature];
            }
        }
        $this->fail("módulo $feature sem grupo");
    }

    public function test_salvar_pela_empresa_grava_no_contrato_dela_e_aparece_para_quem_divide_o_contrato(): void
    {
        $this->superAdmin();
        $plan = Plan::create(['name' => 'C '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets' => true]]);
        $a = $this->empresa($plan, 'Empresa A');
        $b = $this->empresa($plan, 'Empresa B');
        [$campo, $feature] = $this->campo('tabela_clients');
        [$campoAssets] = $this->campo('tabela_assets');

        Livewire::test(EditTenant::class, ['record' => $a->id])
            ->mountAction('modulos_do_contrato')
            ->assertSee('Empresa B')
            ->assertActionDataSet([$campoAssets => ['tabela_assets']])
            ->setActionData([$campoAssets => ['tabela_assets'], $campo => [$feature]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $plan->refresh();
        $this->assertTrue($plan->features['tabela_clients']);
        $this->assertTrue($plan->features['tabela_assets']);
        $this->assertTrue($b->refresh()->plan->hasFeature('tabela_clients'), 'quem divide o contrato recebe a mudança');
    }

    public function test_desmarcar_remove_do_contrato(): void
    {
        $this->superAdmin();
        $plan = Plan::create(['name' => 'C '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets' => true, 'tabela_clients' => true]]);
        $a = $this->empresa($plan, 'Empresa A');
        [$campoAssets] = $this->campo('tabela_assets');
        [$campoClients] = $this->campo('tabela_clients');

        Livewire::test(EditTenant::class, ['record' => $a->id])
            ->callAction('modulos_do_contrato', [$campoAssets => ['tabela_assets'], $campoClients => []]);

        $features = $plan->refresh()->features;
        $this->assertTrue($features['tabela_assets']);
        $this->assertArrayNotHasKey('tabela_clients', array_filter($features));
    }
}
