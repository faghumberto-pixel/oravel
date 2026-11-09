<?php

namespace Tests\Feature;

use App\Dashboards\Blocks\Frota\FrotaCampoBlock;
use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Pages\VisaoGeralLogisticaFrota;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MenuModules;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** "Visão geral — Logística da Frota" é opcional por contrato: sem a chave não aparece, não entra em pacote e a Central consegue ligar. */
class FrotaLogisticaOpcionalTest extends TestCase
{
    use DatabaseTransactions;

    private const MODULOS = ['tabela_assets', 'tabela_frota_saidas_veiculo', 'tabela_frota_abastecimentos', 'tabela_maintenance_orders'];

    private function entrar(array $features): void
    {
        config(['oravel.dashboards_modulo' => true]);
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features + ['modulo_paineis_funcao' => true]]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'fl-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_sem_a_chave_a_tela_nao_aparece_nem_abre(): void
    {
        $this->entrar(self::MODULOS);

        $this->assertFalse(VisaoGeralLogisticaFrota::canAccess());
        $this->get('/admin/visao-geral-logistica-frota')->assertForbidden();
    }

    public function test_com_a_chave_a_tela_aparece_e_abre(): void
    {
        $this->entrar(array_merge(self::MODULOS, [FrotaCampoBlock::CHAVE]));

        $this->assertTrue(VisaoGeralLogisticaFrota::canAccess());
        $this->get('/admin/visao-geral-logistica-frota')->assertOk();
    }

    public function test_nenhum_pacote_inclui_a_tela_e_a_central_oferece_a_chave_no_contrato(): void
    {
        foreach (array_keys(Pacotes::todos()) as $codigo) {
            $this->assertNotContains(FrotaCampoBlock::CHAVE, Pacotes::features($codigo), $codigo);
        }

        $this->assertArrayHasKey(FrotaCampoBlock::CHAVE, MenuModules::all());
        $this->assertSame('visao-geral-logistica-frota', MenuModules::all()[FrotaCampoBlock::CHAVE]['slug']);
        $this->assertArrayHasKey(FrotaCampoBlock::CHAVE, ContratoResource::groupedFeatureOptions()['Gestão de Frota']);
    }
}
