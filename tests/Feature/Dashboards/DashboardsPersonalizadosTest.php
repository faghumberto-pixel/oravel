<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Blocks\Manutencao\AtendimentosNoPrazo;
use App\Dashboards\Personalizacao\DashboardResolver;
use App\Filament\Pages\DashboardsDaEmpresa;
use App\Filament\Pages\VisaoGeralManutencao;
use App\Models\Asset;
use App\Models\DashboardAssignment;
use App\Models\DashboardTemplate;
use App\Models\DashboardTemplateBlock;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardsPersonalizadosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_personalizados' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(array $features = ['tabela_assets' => true, 'tabela_maintenance_orders' => true]): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'dp-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    /** @param list<string|array{0: string, 1: array}> $blocos */
    private function modelo(string $nome, array $blocos, ?Tenant $dono = null, array $attrs = []): DashboardTemplate
    {
        $t = DashboardTemplate::withoutGlobalScopes()->create($attrs + ['name' => $nome, 'tenant_id' => $dono?->id, 'profile' => 'direcao']);
        foreach ($blocos as $i => $b) {
            [$chave, $config] = is_array($b) ? $b : [$b, []];
            DashboardTemplateBlock::create(['dashboard_template_id' => $t->id, 'block_key' => $chave, 'position' => $i, 'config' => $config]);
        }

        return $t;
    }

    private function atribuir(Tenant $t, DashboardTemplate $m): void
    {
        DashboardAssignment::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'dashboard_template_id' => $m->id]);
    }

    // ── o que a empresa vê ──

    public function test_empresa_ve_o_modelo_da_plataforma_atribuido_a_ela_e_so_ela(): void
    {
        [$a, $adminA] = $this->empresa();
        [, $adminB] = $this->empresa();
        $this->atribuir($a, $this->modelo('Direção', ['ativos.total']));

        $this->actingAs($adminA);
        $this->assertCount(1, DashboardResolver::visiveisPara($adminA));

        $this->actingAs($adminB);
        $this->assertCount(0, DashboardResolver::visiveisPara($adminB));
        $this->assertFalse(DashboardsDaEmpresa::canAccess());
    }

    public function test_modelo_exclusivo_de_outra_empresa_nunca_aparece_nem_com_atribuicao_forjada(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $exclusivoDeB = $this->modelo('Só da B', ['ativos.total'], $b);
        $this->atribuir($a, $exclusivoDeB); // atribuição indevida, como num dado forjado

        $this->actingAs($adminA);

        $this->assertCount(0, DashboardResolver::templatesFor($a));
        $this->assertFalse(DashboardsDaEmpresa::canAccess());
    }

    public function test_modelo_inativo_nao_aparece(): void
    {
        [$a, $admin] = $this->empresa();
        $this->atribuir($a, $this->modelo('Inativo', ['ativos.total'], null, ['is_active' => false]));
        $this->actingAs($admin);

        $this->assertCount(0, DashboardResolver::visiveisPara($admin));
    }

    public function test_so_aparecem_blocos_de_modulos_contratados(): void
    {
        [$a, $admin] = $this->empresa(['tabela_assets' => true]); // sem Ordens de Serviço
        $m = $this->modelo('Misto', ['manutencao.atendimentos_hoje', 'ativos.total', 'materiais.valor_em_estoque']);
        $this->atribuir($a, $m);
        $this->actingAs($admin);

        $chaves = array_column(DashboardResolver::blocks($m->fresh(), $admin), 'key');

        $this->assertSame(['ativos.total'], $chaves);
    }

    public function test_dashboard_so_com_blocos_de_modulo_nao_contratado_nao_aparece_para_a_empresa(): void
    {
        [$a, $admin] = $this->empresa(['tabela_assets' => true]);
        $this->atribuir($a, $this->modelo('Só manutenção', ['manutencao.atendimentos_hoje']));
        $this->actingAs($admin);

        $this->assertCount(0, DashboardResolver::visiveisPara($admin));
    }

    public function test_sem_atribuicao_nao_ha_dashboard_personalizado_e_o_padrao_do_modulo_continua(): void
    {
        [, $admin] = $this->empresa();
        config(['oravel.dashboards_modulo' => true]);
        $this->actingAs($admin);

        $this->assertFalse(DashboardsDaEmpresa::canAccess());
        $this->assertTrue(VisaoGeralManutencao::canAccess());
    }

    public function test_com_a_flag_desligada_nada_aparece(): void
    {
        [$a, $admin] = $this->empresa();
        $this->atribuir($a, $this->modelo('Direção', ['ativos.total']));
        config(['oravel.dashboards_personalizados' => false]);
        $this->actingAs($admin);

        $this->assertFalse(DashboardsDaEmpresa::canAccess());
        $this->assertCount(0, DashboardResolver::visiveisPara($admin));
    }

    // ── configuração do bloco ──

    public function test_merge_de_config_ignora_chave_desconhecida_e_converte_o_tipo(): void
    {
        $config = AtendimentosNoPrazo::mergeConfig(['meta' => '75', 'inexistente' => 'x', 'dias' => '']);

        $this->assertSame(['meta' => 75, 'dias' => 30], $config);
        $this->assertSame(['meta' => 90, 'dias' => 30], AtendimentosNoPrazo::mergeConfig(null));
    }

    public function test_a_configuracao_do_modelo_chega_ao_bloco(): void
    {
        [$a, $admin] = $this->empresa();
        $m = $this->modelo('SLA', [['manutencao.no_prazo', ['meta' => 50]]]);
        $this->atribuir($a, $m);
        $os = MaintenanceOrder::create([
            'tenant_id' => $a->id, 'asset_id' => Asset::create(['tenant_id' => $a->id, 'name' => 'X', 'status' => 'disponivel'])->id,
            'status' => 'Concluída', 'maintenance_type' => 'Corretiva', 'description' => 't',
        ]);
        $os->forceFill(['sla_target_minutes' => 120, 'finished_at' => now(), 'created_at' => now()->subMinutes(60)])->saveQuietly();
        $this->actingAs($admin);

        $bloco = DashboardResolver::blocks($m->fresh(), $admin)[0];

        $this->assertStringContainsString('meta 50%', $bloco['payload']['delta']);
    }

    // ── escopo dos models ──

    public function test_usuario_da_empresa_le_modelos_da_plataforma_e_os_proprios_mas_nunca_os_de_outra(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $plataforma = $this->modelo('Plataforma', ['ativos.total']);
        $daA = $this->modelo('Da A', ['ativos.total'], $a);
        $daB = $this->modelo('Da B', ['ativos.total'], $b);

        $this->actingAs($adminA);
        $ids = DashboardTemplate::pluck('id')->all();

        $this->assertContains($plataforma->id, $ids);
        $this->assertContains($daA->id, $ids);
        $this->assertNotContains($daB->id, $ids);
    }

    public function test_atribuicoes_so_do_proprio_tenant(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $m = $this->modelo('Direção', ['ativos.total']);
        $this->atribuir($a, $m);
        $this->atribuir($b, $m);

        $this->actingAs($adminA);

        $this->assertSame([$a->id], DashboardAssignment::pluck('tenant_id')->all());
    }

    // ── tela ──

    public function test_a_pagina_mostra_os_dashboards_atribuidos_sem_expor_a_ficha(): void
    {
        [$a, $admin] = $this->empresa();
        Asset::create(['tenant_id' => $a->id, 'name' => 'Gerador', 'status' => 'disponivel']);
        $m = $this->modelo('Painel da Direção', ['ativos.total'], null, ['description' => 'Visão de quem decide']);
        $m->blocks()->first()->update(['ficha_dor' => 'DOR-SECRETA', 'ficha_indicador' => 'IND-SECRETO', 'ficha_meta' => 'META-SECRETA']);
        $this->atribuir($a, $m);
        $this->atribuir($a, $this->modelo('Painel do Estoque', ['ativos.total']));
        $this->actingAs($admin);

        Livewire::test(DashboardsDaEmpresa::class)
            ->assertSee('Painel da Direção')
            ->assertSee('Painel do Estoque')
            ->assertSee('Ativos cadastrados')
            ->assertDontSee('DOR-SECRETA')
            ->assertDontSee('IND-SECRETO')
            ->assertDontSee('META-SECRETA');
    }

    public function test_aba_de_dashboard_alheio_cai_no_primeiro_atribuido(): void
    {
        [$a, $admin] = $this->empresa();
        [$b] = $this->empresa();
        $this->atribuir($a, $this->modelo('Painel A', ['ativos.total']));
        $alheio = $this->modelo('Painel Secreto da B', ['ativos.total'], $b);
        $this->actingAs($admin);

        Livewire::test(DashboardsDaEmpresa::class)
            ->call('selecionar', $alheio->id)
            ->assertSee('Ativos cadastrados')
            ->assertDontSee('Painel Secreto da B');
    }
}
