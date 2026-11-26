<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Central\Resources\ContratoResource\Pages\CreateContrato;
use App\Filament\Central\Resources\ContratoResource\Pages\EditContrato;
use App\Models\Plan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Central → Contrato → "Dashboards liberados": marcar um dashboard liga no contrato o que ele precisa, e o contrato continua sendo a fonte única. */
class CentralContratoDashboardsLiberadosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => null]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    public function test_marcar_o_dashboard_de_vendas_num_contrato_sem_vendas_liga_o_crm_e_salva(): void
    {
        Livewire::test(CreateContrato::class)
            ->fillForm(['name' => 'Contrato Manutenção + Vendas '.uniqid(), 'base_price' => 300, 'billing_cycle' => 'monthly', 'features_group_manutencao' => ['tabela_maintenance_orders']])
            ->fillForm(['dashboards_liberados' => ['vendas']])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = Plan::where('name', 'like', 'Contrato Manutenção + Vendas%')->firstOrFail();
        foreach (ContratoResource::featuresDoDashboard('vendas') as $feature) {
            $this->assertTrue((bool) ($plan->features[$feature] ?? false), "o contrato deveria ter $feature");
        }
        $this->assertTrue((bool) ($plan->features['tabela_maintenance_orders'] ?? false), 'o que já estava marcado continua');
        $this->assertArrayNotHasKey('dashboards_liberados', $plan->getAttributes(), 'a caixa não é gravada: o contrato (features) é a fonte única');
    }

    public function test_ao_editar_a_caixa_vem_marcada_so_quando_o_contrato_tem_tudo_que_o_dashboard_precisa(): void
    {
        $completo = Plan::create(['name' => 'Completo '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => array_fill_keys(ContratoResource::featuresDoDashboard('vendas'), true)]);
        $vazio = Plan::create(['name' => 'Sem CRM '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients' => true]]);

        $this->assertContains('vendas', Livewire::test(EditContrato::class, ['record' => $completo->getKey()])->get('data.dashboards_liberados'));
        $this->assertNotContains('vendas', Livewire::test(EditContrato::class, ['record' => $vazio->getKey()])->get('data.dashboards_liberados'));
    }
}
