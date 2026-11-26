<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\DashboardRenderer;
use App\Dashboards\VisaoEmpresa;
use App\Filament\Pages\PainelGestao;
use App\Models\CrmLead;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/** "Painel de Gestão": visão da empresa inteira, montada só com as áreas que o contrato cobre. */
class DashboardsEmpresaTest extends TestCase
{
    use DatabaseTransactions;

    private const CRM = ['tabela_crm_leads' => true];

    private const FINANCEIRO = ['tabela_account_receivables' => true, 'tabela_account_payables' => true];

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(array $features): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => $features + ['modulo_dashboard' => true, 'modulo_paineis_funcao' => true]]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 've-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function chaves(array $resumo): array
    {
        return array_column($resumo['secoes'], 'key');
    }

    public function test_so_aparecem_as_areas_que_o_contrato_cobre(): void
    {
        [, $admin] = $this->empresa(self::CRM + self::FINANCEIRO);
        $this->actingAs($admin);

        $resumo = VisaoEmpresa::resumo($admin);

        $this->assertSame(['comercial', 'financeiro'], $this->chaves($resumo));
        $this->assertCount(2, $resumo['destaques'], 'um destaque por área');
        $this->assertNotContains('manutencao', $this->chaves($resumo));
    }

    public function test_contrato_sem_nenhuma_area_nao_tem_visao_da_empresa(): void
    {
        [, $admin] = $this->empresa(['tabela_clients' => true]);
        $this->actingAs($admin);

        $this->assertSame([], VisaoEmpresa::resumo($admin)['secoes']);
        $this->assertFalse(VisaoEmpresa::disponivel($admin));
    }

    public function test_os_numeros_sao_so_da_propria_empresa(): void
    {
        [$a, $adminA] = $this->empresa(self::CRM);
        [$b] = $this->empresa(self::CRM);
        CrmLead::unguarded(fn () => CrmLead::create(['tenant_id' => $a->id, 'name' => 'A', 'stage' => 'novo', 'estimated_value' => 10000]));
        CrmLead::unguarded(fn () => CrmLead::create(['tenant_id' => $b->id, 'name' => 'B', 'stage' => 'novo', 'estimated_value' => 5000000]));
        $this->actingAs($adminA);

        $blocos = collect(VisaoEmpresa::resumo($adminA)['secoes'][0])->only(['kpis', 'graficos', 'listas'])->flatten(1)->keyBy('key');
        $pipeline = $blocos['comercial.pipeline']['payload'];

        $this->assertSame('R$ 10,0 mil', $pipeline['value'], 'o pipeline de outra empresa (R$ 5 mi) não entra');
    }

    public function test_tabelas_e_listas_nunca_dividem_a_linha_dos_graficos(): void
    {
        [, $admin] = $this->empresa(self::CRM + self::FINANCEIRO);
        $this->actingAs($admin);

        foreach (VisaoEmpresa::resumo($admin)['secoes'] as $secao) {
            foreach ($secao['graficos'] as $b) {
                $this->assertNotContains($b['payload']['type'], ['table', 'timeline', 'alerts', 'map', 'heat'], $secao['key'].': '.$b['key']);
            }
            foreach ($secao['listas'] as $b) {
                $this->assertContains($b['payload']['type'], ['table', 'timeline', 'alerts', 'map', 'heat']);
                $this->assertGreaterThanOrEqual(6, $b['size']);
            }
        }
    }

    public function test_a_grade_sempre_fecha_as_12_colunas(): void
    {
        [, $admin] = $this->empresa(self::CRM + self::FINANCEIRO);
        $this->actingAs($admin);

        foreach (VisaoEmpresa::resumo($admin)['secoes'] as $secao) {
            foreach (['kpis', 'graficos', 'listas'] as $grupo) {
                if ($secao[$grupo]) {
                    $soma = array_sum(array_column($secao[$grupo], 'size'));
                    // Fecha as 12 colunas; a única exceção é um gráfico sozinho, que fica em meia largura (um anel em 12 colunas ficaria vazio).
                    $esperado = ($grupo === 'graficos' && count($secao[$grupo]) === 1) ? 6 : 12;
                    $this->assertSame($esperado, $soma, $secao['key'].' '.$grupo.' = '.$soma);
                }
            }
        }
    }

    public function test_resumo_distribui_os_cartoes_em_linhas_equilibradas(): void
    {
        $rf = new \ReflectionMethod(VisaoEmpresa::class, 'distribuir');
        $spans = fn (int $n) => array_column($rf->invoke(null, array_fill(0, $n, ['x' => 1])), 'span');

        $this->assertSame([12], $spans(1));
        $this->assertSame([6, 6], $spans(2));
        $this->assertSame([3, 3, 3, 3], $spans(4));
        $this->assertSame([4, 4, 4, 6, 6], $spans(5));
        $this->assertSame([3, 3, 3, 3, 4, 4, 4], $spans(7));
        $this->assertSame([3, 3, 3, 3, 3, 3, 3, 3], $spans(8));
    }

    public function test_a_aba_painel_de_gestao_mostra_a_visao_da_empresa_ou_o_padrao_antigo(): void
    {
        [, $admin] = $this->empresa(self::CRM + self::FINANCEIRO);
        $this->actingAs($admin);

        Livewire::test(PainelGestao::class)->assertSee('Resumo da empresa')->assertSee('Comercial e vendas')->assertSee('Financeiro');

        config(['oravel.dashboards_modulo' => false]);
        Livewire::test(PainelGestao::class)->assertDontSee('Resumo da empresa');
    }

    public function test_atalhos_de_periodo_da_gestao_a_vista(): void
    {
        [, $admin] = $this->empresa(self::CRM);
        $this->actingAs($admin);

        $pagina = Livewire::test(PainelGestao::class);
        $this->assertSame(1, $pagina->instance()->periodoAtivo(), 'o padrão é o mês atual');

        $pagina->call('periodo', 6);
        $this->assertSame(6, $pagina->instance()->periodoAtivo());
        $this->assertSame(now()->startOfMonth()->subMonths(5)->toDateString(), $pagina->get('gestaoFrom'));
        $this->assertSame(now()->endOfMonth()->toDateString(), $pagina->get('gestaoUntil'));

        $pagina->call('periodo', 99);
        $this->assertSame(1, $pagina->instance()->periodoAtivo(), 'valor inválido volta ao mês atual');

        $pagina->set('gestaoFrom', '2026-01-15');
        $this->assertNull($pagina->instance()->periodoAtivo(), 'datas escolhidas à mão não marcam atalho');
    }

    public function test_gestao_a_vista_de_contrato_sem_manutencao_mostra_o_comercial(): void
    {
        [, $semManutencao] = $this->empresa(self::CRM);
        $this->actingAs($semManutencao);
        $this->assertNotEmpty(Livewire::test(PainelGestao::class)->instance()->getGestaoComercial());
        Livewire::test(PainelGestao::class)->call('selectTab', 'comando')->assertSee('Indicadores de Resultados – Comercial')->assertDontSee('Manutenção Realizada');

        [, $comManutencao] = $this->empresa(self::CRM + ['tabela_maintenance_orders' => true]);
        $this->actingAs($comManutencao);
        $this->assertNull(Livewire::test(PainelGestao::class)->instance()->getGestaoComercial(), 'com manutenção, a Gestão à Vista continua sendo a de manutenção');
    }

    public function test_com_uma_area_so_nao_ha_resumo_e_o_numero_principal_fica_na_secao(): void
    {
        [, $admin] = $this->empresa(self::CRM);
        $this->actingAs($admin);

        $resumo = VisaoEmpresa::resumo($admin);

        $this->assertSame([], $resumo['destaques'], 'um cartão gigante repetindo a seção não ajuda');
        $this->assertSame(['comercial'], $this->chaves($resumo));
        Livewire::test(PainelGestao::class)->assertDontSee('Resumo da empresa')->assertSee('Comercial e vendas');
    }

    public function test_as_linhas_dos_dashboards_fecham_as_12_colunas_exceto_a_ultima(): void
    {
        $bloco = fn (int $size) => ['key' => 'x', 'title' => '', 'subtitle' => '', 'size' => $size, 'payload' => []];
        $tamanhos = fn (array $in) => array_column(DashboardRenderer::ajustarLinhas(array_map($bloco, $in)), 'size');

        // 3+4+4 (11) vira 4+4+4; 6+4 (10) vira 6+6; a última linha (4+4) fica como está.
        $this->assertSame([4, 4, 4, 6, 6, 4, 4], $tamanhos([3, 4, 4, 6, 4, 4, 4]));
        // O que já fecha não muda, e um bloco de 12 também não.
        $this->assertSame([6, 6, 12, 3, 3, 3, 3], $tamanhos([6, 6, 12, 3, 3, 3, 3]));
        $this->assertSame([], $tamanhos([]));
    }
}
