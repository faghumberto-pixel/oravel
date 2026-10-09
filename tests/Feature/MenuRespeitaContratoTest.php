<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\SaaSRegistry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class MenuRespeitaContratoTest extends TestCase
{
    use DatabaseTransactions;

    /** Rótulos de todos os itens do menu (inclusive filhos) que o administrador do contrato enxerga. */
    private function menu(array $desligar): array
    {
        $features = [];
        foreach (SaaSRegistry::modules() as $m) {
            if ($m['feature']) {
                $features[$m['feature']] = true;
            }
        }
        foreach (array_keys(config('menu_modules')) as $k) {
            $features[$k] = true;
        }
        foreach ($desligar as $k) {
            $features[$k] = false;
        }

        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@t.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]));

        Auth::login($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app()->forgetInstance(\Filament\Navigation\NavigationManager::class);

        $rotulos = [];
        foreach (Filament::getNavigation() as $grupo) {
            foreach ($grupo->getItems() as $item) {
                $rotulos[] = $item->getLabel();
                foreach ($item->getChildItems() as $filho) {
                    $rotulos[] = $filho->getLabel();
                }
            }
        }

        return $rotulos;
    }

    public function test_tela_solta_dentro_de_menu_liberado_some_quando_desmarcada(): void
    {
        $this->assertContains('Reservas Urgentes', $this->menu([]));
        $this->assertNotContains('Reservas Urgentes', $this->menu(['menu_reservas_urgentes']));
    }

    public function test_conciliacao_bancaria_exige_o_proprio_modulo_mesmo_com_contas_a_receber(): void
    {
        $this->assertContains('Conciliação Bancária', $this->menu([]));
        $this->assertNotContains('Conciliação Bancária', $this->menu(['tabela_bank_reconciliation']));
        $this->assertContains('Fluxo de Caixa', $this->menu(['tabela_bank_reconciliation']), 'desmarcar um não afeta o outro');
    }
}
