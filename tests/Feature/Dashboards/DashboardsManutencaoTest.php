<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Block;
use App\Dashboards\BlockRegistry;
use App\Dashboards\Blocks\Manutencao\AtendimentosHoje;
use App\Dashboards\Blocks\Manutencao\AtendimentosNoPrazo;
use App\Dashboards\Blocks\Manutencao\EquipamentosParados;
use App\Dashboards\Blocks\Manutencao\KmRodados;
use App\Dashboards\DashboardRenderer;
use App\Dashboards\ModuleDashboards;
use App\Filament\Pages\VisaoGeralManutencao;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HubMenus;
use App\Support\SaaSRegistry;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardsManutencaoTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(array $features = ['tabela_maintenance_orders' => true, 'tabela_assets' => true]): array
    {
        $plan = Plan::create([
            'name' => 'Plano Dash '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);
        $tenant = Tenant::create(['name' => 'Empresa '.uniqid(), 'slug' => 'dash-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.test', 'password' => bcrypt('x'),
            'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function os(Tenant $tenant, array $attrs = []): MaintenanceOrder
    {
        $ativo = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo '.uniqid(), 'status' => 'disponivel']);

        $os = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $ativo->id, 'status' => 'Aberto', 'maintenance_type' => 'Corretiva',
            'description' => 'Teste', 'scheduled_at' => now(),
        ]);

        // Colunas fora do $fillable (km, SLA, datas) entram à força, como o app faz internamente.
        return $os->forceFill($attrs)->saveQuietly() ? $os->refresh() : $os;
    }

    // ── catálogo ──

    public function test_catalogo_tem_chaves_unicas_tamanhos_validos_e_modulos_que_existem(): void
    {
        $chaves = array_keys(BlockRegistry::all());
        $this->assertSame($chaves, array_unique($chaves));

        $features = collect(SaaSRegistry::modules())->pluck('feature')->filter()->all();

        foreach (BlockRegistry::all() as $chave => $classe) {
            $this->assertSame($chave, $classe::key());
            $this->assertContains($classe::size(), Block::SIZES, "$chave: tamanho fora da grade");
            $this->assertContains($classe::requiredFeature(), $features, "$chave: módulo exigido não existe no SaaSRegistry");
            $this->assertNotSame('', $classe::title());
        }

        foreach (ModuleDashboards::all() as $dashboard) {
            foreach ($dashboard['blocks'] as $chave) {
                $this->assertArrayHasKey($chave, BlockRegistry::all(), "dashboard cita bloco inexistente: $chave");
            }
        }
    }

    // ── flag e módulo contratado ──

    public function test_com_a_flag_desligada_a_pagina_nao_aparece_nem_abre(): void
    {
        [, $admin] = $this->empresa();
        config(['oravel.dashboards_modulo' => false]);
        $this->actingAs($admin);

        $this->assertFalse(VisaoGeralManutencao::canAccess());
        $this->assertFalse(VisaoGeralManutencao::shouldRegisterNavigation());
    }

    public function test_tenant_sem_o_modulo_no_contrato_nao_ve_a_pagina_nem_os_blocos(): void
    {
        [, $admin] = $this->empresa(['tabela_assets' => true]);
        $this->actingAs($admin);

        $this->assertFalse(VisaoGeralManutencao::canAccess());
        $this->assertSame([], DashboardRenderer::blocks('manutencao', $admin));
    }

    public function test_tenant_com_o_modulo_ve_a_pagina_e_os_12_blocos(): void
    {
        [, $admin] = $this->empresa();
        $this->actingAs($admin);

        $this->assertTrue(VisaoGeralManutencao::canAccess());
        $this->assertCount(12, DashboardRenderer::blocks('manutencao', $admin));
    }

    public function test_usuario_sem_permissao_de_leitura_nao_ve_blocos(): void
    {
        [$tenant] = $this->empresa();
        $comum = User::create([
            'name' => 'Comum', 'email' => 'comum-'.uniqid().'@oravel.test', 'password' => bcrypt('x'),
            'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $this->actingAs($comum);

        $this->assertSame([], DashboardRenderer::blocks('manutencao', $comum));
        $this->assertFalse(VisaoGeralManutencao::canAccess());
    }

    // ── isolamento por tenant ──

    public function test_blocos_so_contam_dados_do_proprio_tenant(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $this->os($a);
        $this->os($b);
        $this->os($b);

        $this->actingAs($adminA);

        $this->assertSame(1, (new AtendimentosHoje)->data([])['value']);
    }

    public function test_cache_nao_vaza_entre_tenants(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b, $adminB] = $this->empresa();
        $this->os($a);
        $this->os($b);
        $this->os($b);

        $this->actingAs($adminA);
        $valorA = collect(DashboardRenderer::blocks('manutencao', $adminA))->firstWhere('key', 'manutencao.atendimentos_hoje')['payload']['value'];

        $this->actingAs($adminB);
        $valorB = collect(DashboardRenderer::blocks('manutencao', $adminB))->firstWhere('key', 'manutencao.atendimentos_hoje')['payload']['value'];

        $this->assertSame([1, 2], [$valorA, $valorB]);
    }

    // ── contas dos blocos ──

    public function test_atendimentos_hoje_separa_por_tipo(): void
    {
        [$tenant, $admin] = $this->empresa();
        $this->os($tenant, ['maintenance_type' => 'Corretiva']);
        $this->os($tenant, ['maintenance_type' => 'Preventiva']);
        $this->os($tenant, ['maintenance_type' => 'Preventiva']);
        $this->os($tenant, ['maintenance_type' => 'Corretiva', 'scheduled_at' => now()->subDays(2)]);
        $this->actingAs($admin);

        $p = (new AtendimentosHoje)->data([]);

        $this->assertSame(3, $p['value']);
        $this->assertStringContainsString('2 preventiva', $p['delta']);
    }

    public function test_no_prazo_calcula_o_percentual_so_das_os_com_sla(): void
    {
        [$tenant, $admin] = $this->empresa();
        $this->os($tenant, ['status' => 'Concluída', 'sla_target_minutes' => 120, 'finished_at' => now(), 'created_at' => now()->subMinutes(60)]);
        $this->os($tenant, ['status' => 'Concluída', 'sla_target_minutes' => 60, 'finished_at' => now(), 'created_at' => now()->subMinutes(180)]);
        $this->os($tenant, ['status' => 'Concluída', 'finished_at' => now()]); // sem SLA: fora da conta
        $this->actingAs($admin);

        $p = (new AtendimentosNoPrazo)->data(['meta' => 90]);

        $this->assertSame('50%', $p['value']);
        $this->assertSame('dn', $p['tone']);
    }

    public function test_equipamentos_parados_conta_so_os_em_manutencao(): void
    {
        [$tenant, $admin] = $this->empresa();
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'A', 'status' => 'manutencao']);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'B', 'status' => 'manutencao']);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'C', 'status' => 'locado']);
        $this->actingAs($admin);

        $this->assertSame(2, (new EquipamentosParados)->data([])['value']);
    }

    public function test_km_rodados_soma_o_mes_e_calcula_o_custo_por_atendimento(): void
    {
        [$tenant, $admin] = $this->empresa();
        $this->os($tenant, ['km_traveled' => 100, 'logistics_cost' => 200]);
        $this->os($tenant, ['km_traveled' => 50, 'logistics_cost' => 100]);
        $this->actingAs($admin);

        $p = (new KmRodados)->data([]);

        $this->assertSame('150', $p['value']);
        $this->assertStringContainsString('150,00', $p['delta']);
    }

    // ── tela ──

    public function test_a_pagina_renderiza_os_blocos_do_exemplo(): void
    {
        [$tenant, $admin] = $this->empresa();
        $this->os($tenant, ['status' => 'Pendente', 'internal_status' => 'aguardando_peca']);
        $this->actingAs($admin);

        Livewire::test(VisaoGeralManutencao::class)
            ->assertSee('Atendimentos hoje')
            ->assertSee('Agenda de hoje')
            ->assertSee('Aguardando peça')
            ->assertSee('Tempo até o primeiro atendimento')
            ->assertSee('Sem pedido');
    }

    // ── clique no grupo do menu ──

    private function principalDeManutencao(): ?string
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        app()->forgetInstance(NavigationManager::class);

        return HubMenus::paginaPrincipalDeCadaGrupo()['Manutenção'] ?? null;
    }

    public function test_abrir_o_grupo_manutencao_leva_a_visao_geral(): void
    {
        [, $admin] = $this->empresa();
        $this->actingAs($admin);

        $this->assertSame(VisaoGeralManutencao::getUrl(), $this->principalDeManutencao());
    }

    public function test_sem_a_flag_o_grupo_manutencao_continua_indo_para_a_pagina_de_antes(): void
    {
        [, $admin] = $this->empresa();
        config(['oravel.dashboards_modulo' => false]);
        $this->actingAs($admin);

        $destino = $this->principalDeManutencao();

        $this->assertNotSame(VisaoGeralManutencao::getUrl(), $destino);
    }
}
