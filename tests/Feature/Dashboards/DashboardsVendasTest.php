<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\DashboardRenderer;
use App\Dashboards\ModuleDashboards;
use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Pages\VisaoGeralVendas;
use App\Models\CrmLead;
use App\Models\CrmMeta;
use App\Models\CrmProposta;
use App\Models\CrmVisita;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Painel "Visão geral — Vendas" (gestão comercial do CRM) e a liberação dele pelo contrato, na Central. */
class DashboardsVendasTest extends TestCase
{
    use DatabaseTransactions;

    private const CRM = [
        'tabela_crm_leads' => true, 'tabela_crm_metas' => true, 'tabela_crm_propostas' => true, 'tabela_crm_visitas' => true,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(array $features = self::CRM): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features + ['modulo_paineis_funcao' => true]]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'vd-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function bloco(string $dashboard, string $chave, User $user): array
    {
        return collect(DashboardRenderer::blocks($dashboard, $user))->firstWhere('key', $chave)['payload'] ?? [];
    }

    public function test_painel_aparece_com_o_crm_e_traz_os_blocos_de_gestao_comercial(): void
    {
        [, $admin] = $this->empresa();
        $this->actingAs($admin);

        $this->assertTrue(VisaoGeralVendas::canAccess());
        $chaves = collect(DashboardRenderer::blocks('vendas', $admin))->pluck('key')->all();
        foreach (['vendas.meta_mes', 'vendas.meta_vendedor', 'vendas.visitas_mes', 'vendas.propostas_aberto', 'vendas.propostas_situacao', 'vendas.ciclo_venda', 'vendas.perdas_motivo', 'vendas.leads_parados', 'comercial.funil', 'comercial.pipeline'] as $esperada) {
            $this->assertContains($esperada, $chaves);
        }
    }

    public function test_sem_o_crm_no_contrato_o_painel_nao_existe(): void
    {
        [, $admin] = $this->empresa(['tabela_clients' => true]);
        $this->actingAs($admin);

        $this->assertFalse(VisaoGeralVendas::canAccess());
        $this->assertSame([], DashboardRenderer::blocks('vendas', $admin));
    }

    public function test_meta_do_mes_compara_o_fechado_com_a_meta_so_do_proprio_tenant(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        CrmMeta::unguarded(fn () => CrmMeta::create(['tenant_id' => $a->id, 'user_id' => $adminA->id, 'mes' => now(), 'meta_valor' => 100000, 'meta_ganhos' => 5, 'meta_visitas' => 20]));
        CrmLead::unguarded(fn () => CrmLead::create(['tenant_id' => $a->id, 'name' => 'Ganho', 'stage' => 'convertido', 'estimated_value' => 40000, 'valor_fechado' => 40000, 'fechado_em' => now(), 'vendedor_fechamento_id' => $adminA->id]));
        CrmLead::unguarded(fn () => CrmLead::create(['tenant_id' => $b->id, 'name' => 'Outro', 'stage' => 'convertido', 'estimated_value' => 999000, 'valor_fechado' => 999000, 'fechado_em' => now()]));
        $this->actingAs($adminA);

        $p = $this->bloco('vendas', 'vendas.meta_mes', $adminA);
        $this->assertSame(40, $p['value'], '40 mil de 100 mil; o fechado de outra empresa não entra');
        $this->assertSame('%', $p['unit']);
    }

    public function test_propostas_e_visitas_contam_so_o_proprio_tenant(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b, $adminB] = $this->empresa();
        CrmProposta::unguarded(fn () => CrmProposta::create(['tenant_id' => $a->id, 'titulo' => 'P1', 'status' => 'enviada', 'total' => 10000, 'validade_ate' => now()->addDays(3)]));
        CrmProposta::unguarded(fn () => CrmProposta::create(['tenant_id' => $b->id, 'titulo' => 'P2', 'status' => 'enviada', 'total' => 77000, 'validade_ate' => now()->addDays(3)]));
        CrmVisita::unguarded(fn () => CrmVisita::create(['tenant_id' => $a->id, 'user_id' => $adminA->id, 'agendada_para' => now()->subHour(), 'status' => 'realizada', 'contato_nome' => 'X']));
        $this->actingAs($adminA);

        $abertas = $this->bloco('vendas', 'vendas.propostas_aberto', $adminA);
        $this->assertStringContainsString('1 propostas', $abertas['delta']);
        $this->assertStringContainsString('1 vencem em 7 dias', $abertas['delta']);
        $this->assertSame(1, $this->bloco('vendas', 'vendas.visitas_mes', $adminA)['value']);

        Cache::flush();
        $this->actingAs($adminB);
        $this->assertSame(0, $this->bloco('vendas', 'vendas.visitas_mes', $adminB)['value']);
    }

    public function test_central_liberar_o_dashboard_liga_no_contrato_as_funcionalidades_que_ele_precisa(): void
    {
        $precisa = ContratoResource::featuresDoDashboard('vendas');
        foreach (['tabela_crm_leads', 'tabela_crm_metas', 'tabela_crm_propostas', 'tabela_crm_visitas', 'modulo_dashboard'] as $f) {
            $this->assertContains($f, $precisa);
        }

        // Contrato sem nada: o dashboard não está liberado.
        $this->assertNotContains('vendas', ContratoResource::dashboardsLiberados(['tabela_clients']));

        // Marcar o dashboard mescla as funcionalidades nos campos por grupo (união, nunca desmarca).
        $estado = [];
        $get = function ($campo) use (&$estado) {
            return $estado[$campo] ?? [];
        };
        $set = function ($campo, $valor) use (&$estado) {
            $estado[$campo] = $valor;
        };
        $estado[ContratoResource::groupFieldName('Comercial')] = ['tabela_quotes'];
        ContratoResource::aplicarDashboards(['vendas'], $get, $set);

        $marcadas = collect($estado)->flatten()->all();
        $this->assertContains('tabela_quotes', $marcadas, 'o que já estava marcado continua');
        foreach (['tabela_crm_leads', 'tabela_crm_metas', 'tabela_crm_propostas', 'tabela_crm_visitas'] as $f) {
            $this->assertContains($f, $marcadas);
        }
        $this->assertContains('vendas', ContratoResource::dashboardsLiberados($marcadas));
    }

    public function test_o_painel_de_vendas_esta_no_catalogo_de_dashboards(): void
    {
        $this->assertArrayHasKey('vendas', ModuleDashboards::all());
        $this->assertSame('Visão geral — Vendas', ModuleDashboards::find('vendas')['label']);
    }
}
