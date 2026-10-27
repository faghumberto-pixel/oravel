<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Personalizacao\DashboardResolver;
use App\Dashboards\Personalizacao\DashboardTemplateService;
use App\Filament\Central\Resources\DashboardTemplateResource\Pages\CreateDashboardTemplate;
use App\Filament\Pages\DashboardsDaEmpresa;
use App\Filament\Pages\PainelGestao;
use App\Models\Asset;
use App\Models\DashboardAssignment;
use App\Models\DashboardTemplate;
use App\Models\DashboardTemplateBlock;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/** A Central escolhe, por empresa, entre o Início personalizado e o padrão (Painel de Controle + Gestão à Vista). */
class DashboardsInicioTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_personalizados' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(array $features = ['tabela_assets' => true]): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'in-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function modelo(string $nome, array $blocos = ['ativos.total'], ?Tenant $dono = null): DashboardTemplate
    {
        $m = DashboardTemplate::withoutGlobalScopes()->create(['name' => $nome, 'tenant_id' => $dono?->id, 'profile' => 'direcao']);
        foreach ($blocos as $i => $chave) {
            DashboardTemplateBlock::create(['dashboard_template_id' => $m->id, 'block_key' => $chave, 'position' => $i, 'config' => []]);
        }

        return $m;
    }

    public function test_sem_inicio_personalizado_a_empresa_segue_com_o_inicio_padrao(): void
    {
        [$t, $admin] = $this->empresa(['tabela_assets' => true, 'modulo_dashboard' => true]);
        DashboardTemplateService::atribuir($this->modelo('Só na página Dashboards'), [$t->id]); // atribuído, mas NÃO como Início
        $this->actingAs($admin);

        $this->assertCount(0, DashboardResolver::visiveisPara($admin, true));

        Livewire::test(PainelGestao::class)
            ->assertViewIs('filament.pages.painel-gestao')
            ->assertDontSee('Ativos cadastrados');
    }

    public function test_com_inicio_personalizado_o_painel_de_controle_e_a_gestao_a_vista_saem_de_cena(): void
    {
        [$t, $admin] = $this->empresa(['tabela_assets' => true, 'modulo_dashboard' => true]);
        Asset::create(['tenant_id' => $t->id, 'name' => 'Gerador', 'status' => 'disponivel']);
        DashboardTemplateService::atribuir($this->modelo('Visão geral da empresa'), [$t->id], true);
        $this->actingAs($admin);

        Livewire::test(PainelGestao::class)
            ->assertViewIs('filament.pages.inicio-personalizado')
            ->assertSee('Ativos cadastrados');
    }

    public function test_outra_empresa_nao_e_afetada(): void
    {
        [$a] = $this->empresa();
        [, $adminB] = $this->empresa(['tabela_assets' => true, 'modulo_dashboard' => true]);
        DashboardTemplateService::atribuir($this->modelo('Da A'), [$a->id], true);
        $this->actingAs($adminB);

        $this->assertCount(0, DashboardResolver::visiveisPara($adminB, true));
        Livewire::test(PainelGestao::class)->assertViewIs('filament.pages.painel-gestao');
    }

    public function test_inicio_personalizado_vale_mesmo_sem_o_painel_de_controle_no_contrato(): void
    {
        [$t, $admin] = $this->empresa(['tabela_assets' => true]); // sem modulo_dashboard
        $this->actingAs($admin);
        $this->assertFalse(PainelGestao::canAccess(), 'sem o Painel de Controle no contrato e sem Início personalizado, não há Início');

        DashboardTemplateService::atribuir($this->modelo('Início da empresa'), [$t->id], true);

        $this->assertTrue(PainelGestao::canAccess(), 'o Início personalizado não depende do contrato do Painel de Controle');
    }

    public function test_varios_dashboards_de_inicio_viram_abas_e_aba_alheia_cai_no_primeiro(): void
    {
        [$t, $admin] = $this->empresa();
        [$b] = $this->empresa();
        DashboardTemplateService::atribuir($this->modelo('Visão geral'), [$t->id], true);
        DashboardTemplateService::atribuir($this->modelo('Assistência técnica'), [$t->id], true);
        $alheio = $this->modelo('Painel Secreto da B', ['ativos.total'], $b);
        $this->actingAs($admin);

        Livewire::test(PainelGestao::class)
            ->assertSee('Visão geral')
            ->assertSee('Assistência técnica')
            ->call('selecionarPainel', $alheio->id)
            ->assertDontSee('Painel Secreto da B')
            ->assertSee('Ativos cadastrados');
    }

    public function test_dashboard_de_inicio_nao_repete_na_pagina_dashboards(): void
    {
        [$t, $admin] = $this->empresa();
        DashboardTemplateService::atribuir($this->modelo('É o Início'), [$t->id], true);
        DashboardTemplateService::atribuir($this->modelo('Fica em Dashboards'), [$t->id]);
        $this->actingAs($admin);

        $this->assertSame(['Fica em Dashboards'], DashboardResolver::visiveisPara($admin)->pluck('name')->all());
        $this->assertSame(['É o Início'], DashboardResolver::visiveisPara($admin, true)->pluck('name')->all());
        $this->assertTrue(DashboardsDaEmpresa::canAccess());
    }

    public function test_reatribuir_como_inicio_ajusta_a_atribuicao_existente_sem_duplicar(): void
    {
        [$t] = $this->empresa();
        $m = $this->modelo('Painel');
        DashboardTemplateService::atribuir($m, [$t->id]);
        $this->assertSame(0, DashboardTemplateService::atribuir($m, [$t->id], true));

        $this->assertSame(1, DashboardAssignment::withoutGlobalScopes()->where('dashboard_template_id', $m->id)->count());
        $this->assertTrue(DashboardAssignment::withoutGlobalScopes()->where('dashboard_template_id', $m->id)->first()->is_home);
    }

    public function test_voltar_ao_padrao_basta_desmarcar_o_inicio(): void
    {
        [$t, $admin] = $this->empresa(['tabela_assets' => true, 'modulo_dashboard' => true]);
        DashboardTemplateService::atribuir($this->modelo('Painel'), [$t->id], true);
        $this->actingAs($admin);
        $this->assertCount(1, DashboardResolver::visiveisPara($admin, true));

        DashboardAssignment::withoutGlobalScopes()->where('tenant_id', $t->id)->update(['is_home' => false]);

        $this->assertCount(0, DashboardResolver::visiveisPara($admin, true));
        Livewire::test(PainelGestao::class)->assertViewIs('filament.pages.painel-gestao');
    }

    public function test_com_a_flag_desligada_o_inicio_padrao_continua_mesmo_com_inicio_marcado(): void
    {
        [$t, $admin] = $this->empresa(['tabela_assets' => true, 'modulo_dashboard' => true]);
        DashboardTemplateService::atribuir($this->modelo('Painel'), [$t->id], true);
        config(['oravel.dashboards_personalizados' => false]);
        $this->actingAs($admin);

        Livewire::test(PainelGestao::class)->assertViewIs('filament.pages.painel-gestao');
    }

    // ── Central ──

    public function test_exclusivo_criado_na_central_pode_nascer_como_inicio(): void
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => null, 'is_approved' => true]);
        config(['oravel.super_admins' => [$super->email]]);
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));
        [$t] = $this->empresa();

        Livewire::test(CreateDashboardTemplate::class)
            ->fillForm(['name' => 'Início da empresa', 'tenant_id' => $t->id, 'usar_como_inicio' => true, 'blocks' => [['block_key' => 'ativos.total', 'config' => []]]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(DashboardAssignment::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail()->is_home);
    }

    // ── comando Rautec ──

    public function test_comando_cria_os_4_dashboards_do_exemplo_e_nao_duplica(): void
    {
        $this->assertSame(0, Artisan::call('dashboards:rautec'));
        $this->assertSame(0, Artisan::call('dashboards:rautec'));

        $modelos = DashboardTemplate::withoutGlobalScopes()->whereNull('tenant_id')->where('name', 'like', 'Rautec —%')->get();

        $this->assertCount(4, $modelos);
        $this->assertEqualsCanonicalizing(
            ['Rautec — Visão geral', 'Rautec — Assistência técnica', 'Rautec — Peças e materiais', 'Rautec — Comercial'],
            $modelos->pluck('name')->all(),
        );
        $assistencia = $modelos->firstWhere('name', 'Rautec — Assistência técnica');
        $this->assertCount(9, $assistencia->blocks);
        $this->assertSame(range(1, 9), $assistencia->blocks->pluck('position')->all());
    }

    public function test_comando_com_empresa_entrega_os_4_como_inicio_dela_e_so_dela(): void
    {
        [$t, $admin] = $this->empresa(['tabela_assets' => true, 'tabela_maintenance_orders' => true, 'tabela_materials' => true, 'tabela_contracts' => true]);
        [, $outroAdmin] = $this->empresa(['tabela_assets' => true, 'tabela_maintenance_orders' => true]);

        $this->assertSame(0, Artisan::call('dashboards:rautec', ['--empresa' => $t->slug]));
        $this->assertSame(0, Artisan::call('dashboards:rautec', ['--empresa' => $t->slug])); // de novo: não duplica

        $this->actingAs($admin);
        $this->assertEqualsCanonicalizing(
            ['Visão geral', 'Assistência técnica', 'Peças e materiais', 'Comercial'],
            DashboardResolver::visiveisPara($admin, true)->pluck('name')->all(),
        );
        $this->assertSame(4, DashboardTemplate::withoutGlobalScopes()->where('tenant_id', $t->id)->count());

        $this->actingAs($outroAdmin);
        $this->assertCount(0, DashboardResolver::visiveisPara($outroAdmin, true));
    }

    public function test_comando_com_empresa_inexistente_falha_sem_criar_nada(): void
    {
        $this->assertSame(1, Artisan::call('dashboards:rautec', ['--empresa' => 'nao-existe-'.uniqid()]));
        $this->assertSame(0, DashboardTemplate::withoutGlobalScopes()->where('name', 'like', 'Rautec —%')->count());
    }
}
