<?php

namespace Tests\Feature;

use App\Filament\Pages\GestaoAtivos;
use App\Filament\Pages\GestaoChecklists;
use App\Filament\Pages\VisaoGeralChecklists;
use App\Models\CkTemplate;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HubMenus;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** O submenu "Checklist" fica no grupo Ativos e abriga a Visao geral de checklists. */
class MenuChecklistEmAtivosTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(array $features): User
    {
        foreach (array_keys(config('menu_modules')) as $k) {
            $features[$k] ??= true;
        }

        $plan = Plan::create(['name' => 'P'.uniqid(), 'price' => 0, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E'.uniqid(), 'slug' => 'e-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $u = User::create(['name' => 'Admin', 'email' => uniqid().'@t.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]));

        return $u;
    }

    /** @return array<string, array<string, list<string>>> grupo => [rotulo do item => rotulos dos filhos] */
    private function menu(): array
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app()->forgetInstance(NavigationManager::class);
        $out = [];

        foreach (Filament::getNavigation() as $grupo) {
            foreach ($grupo->getItems() as $item) {
                $out[$grupo->getLabel() ?? ''][$item->getLabel()] = collect($item->getChildItems())->map->getLabel()->all();
            }
        }

        return $out;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('banco nao descartavel');
        }
    }

    public function test_visao_geral_fica_no_submenu_checklist_dentro_de_ativos_e_nao_mais_em_relatorios(): void
    {
        $this->actingAs($this->admin([CkTemplate::FEATURE_KEY => true, 'tabela_ck_execucoes' => true, 'tabela_assets' => true]));
        $menu = $this->menu();

        $this->assertSame(['Equipamentos', 'Serviços', 'Corretivas × checklist', 'Checklists preenchidos', 'Modelos de equipamentos'], $menu['Ativos']['Checklist']);
        $this->assertArrayNotHasKey('Visão geral de checklists', $menu['Ativos'], 'nao fica solta no grupo: so como filha');

        foreach ($menu['Relatórios'] ?? [] as $filhos) {
            $this->assertNotContains('Visão geral de checklists', $filhos);
        }
    }

    public function test_a_pagina_do_submenu_abre_com_o_cartao_da_visao_geral_e_o_grupo_ativos_continua_abrindo_em_gestao_de_ativos(): void
    {
        $this->actingAs($this->admin([CkTemplate::FEATURE_KEY => true, 'tabela_ck_execucoes' => true, 'tabela_assets' => true]));
        $this->menu();

        $this->get(GestaoChecklists::getUrl())->assertOk()->assertSee('Equipamentos')->assertSee('Serviços')->assertSee('Corretivas × checklist')->assertSee(e(VisaoGeralChecklists::getUrl(['aba' => 'servicos'])), false)->assertSee('hub-card', false);
        $this->assertSame(GestaoAtivos::getUrl(), HubMenus::paginaPrincipalDeCadaGrupo()['Ativos'] ?? null);
    }

    public function test_sem_a_chave_dos_checklists_o_submenu_nao_aparece(): void
    {
        $this->actingAs($this->admin(['tabela_assets' => true]));

        $this->assertArrayNotHasKey('Checklist', $this->menu()['Ativos'] ?? []);
        $this->assertFalse(GestaoChecklists::canAccess());
        $this->get(GestaoChecklists::getUrl())->assertForbidden();
    }

    public function test_cliente_so_com_checklist_de_frota_ou_so_com_os_antigos_continua_achando_o_que_tem_dentro_de_checklist(): void
    {
        $this->actingAs($this->admin(['tabela_frota_checklists' => true, 'tabela_frota_modelos_checklist' => true, 'tabela_assets' => true]));
        $this->assertSame(['Veículos', 'Modelos de veículos'], $this->menu()['Ativos']['Checklist']);
        $this->assertTrue(GestaoChecklists::canAccess());

        $this->actingAs($this->admin(['tabela_checklist_templates' => true, 'tabela_assets' => true]));
        $this->assertSame(['Checklists antigos'], $this->menu()['Ativos']['Checklist']);
    }

    public function test_tudo_de_checklist_foi_para_o_menu_pai_e_saiu_dos_lugares_antigos(): void
    {
        $this->actingAs($this->admin([
            CkTemplate::FEATURE_KEY => true, 'tabela_ck_execucoes' => true, 'tabela_frota_checklists' => true, 'tabela_frota_modelos_checklist' => true,
            'tabela_checklist_templates' => true, 'tabela_assets' => true,
        ]));
        $menu = $this->menu();

        $this->assertSame(
            ['Equipamentos', 'Serviços', 'Corretivas × checklist', 'Veículos', 'Checklists preenchidos', 'Modelos de equipamentos', 'Modelos de veículos', 'Checklists antigos'],
            $menu['Ativos']['Checklist'],
        );

        foreach (['Manutenção', 'Relatórios', 'Gestão de Frota'] as $grupo) {
            foreach ($menu[$grupo] ?? [] as $rotulo => $filhos) {
                foreach (array_merge([$rotulo], $filhos) as $r) {
                    $this->assertDoesNotMatchRegularExpression('/checklist/i', $r, "'$r' ainda aparece no grupo $grupo");
                }
            }
        }
    }

    public function test_pacotes_seguem_a_regra_do_grupo_ativos_e_logistica_nao_perde_os_checklists_da_frota(): void
    {
        $frota = ['tabela_frota_checklists', 'tabela_frota_modelos_checklist'];
        $todos = array_merge($frota, ['tabela_ck_execucoes']);

        $this->assertEmpty(array_diff($frota, Pacotes::features('logistica')), 'Logistica continua com os checklists da Frota');
        $this->assertEmpty(array_diff($todos, Pacotes::features('manutencao')), 'Manutencao inclui o grupo Ativos inteiro, onde agora ficam os checklists');
        $this->assertEmpty(array_diff($todos, Pacotes::features('servicos')));
        $this->assertEmpty(array_diff($todos, Pacotes::features('locadora')), 'ERP completo tem tudo');
        $this->assertEmpty(array_intersect($todos, Pacotes::features('estoque')));
    }
}
