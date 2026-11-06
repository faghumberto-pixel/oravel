<?php

namespace Tests\Feature;

use App\Dashboards\BlockRegistry;
use App\Dashboards\Personalizacao\DashboardInteligenciaDeAtivos;
use App\Dashboards\Personalizacao\DashboardResolver;
use App\Filament\Central\Resources\DashboardTemplateResource\Pages\ListDashboardTemplates;
use App\Filament\Pages\PainelGestao;
use App\Models\DashboardAssignment;
use App\Models\DashboardTemplate;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Demo\LocadoraDemoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Dashboard "Inteligencia de Ativos": modelo na Central, atribuido so' a Locadora Demo, formatos visuais novos. */
class DashboardInteligenciaAtivosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_personalizados' => true]);
    }

    private function entrarNaDemo(): User
    {
        app(LocadoraDemoService::class)->criar('x');
        $admin = User::withoutGlobalScopes()->where('email', LocadoraDemoService::EMAIL_ADMIN)->firstOrFail();
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    private function modelo(): DashboardTemplate
    {
        return DashboardTemplate::withoutGlobalScopes()->whereNull('tenant_id')->where('name', DashboardInteligenciaDeAtivos::NOME)->firstOrFail();
    }

    public function test_modelo_da_plataforma_fica_na_central_e_so_a_demo_recebe(): void
    {
        $this->entrarNaDemo();
        $modelo = $this->modelo();

        $this->assertTrue($modelo->isPlatform());
        $this->assertCount(13, $modelo->blocks);
        foreach (DashboardInteligenciaDeAtivos::BLOCOS as $chave) {
            $this->assertNotNull(BlockRegistry::find($chave), "bloco {$chave} no catalogo");
        }

        $atribuicoes = DashboardAssignment::withoutGlobalScopes()->where('dashboard_template_id', $modelo->id)->get();
        $this->assertCount(1, $atribuicoes);
        $this->assertSame((string) app(LocadoraDemoService::class)->tenant()->id, (string) $atribuicoes->first()->tenant_id);
        $this->assertTrue((bool) $atribuicoes->first()->is_home);

        // Aparece na lista de dashboards personalizados da Central (para atribuir a outros clientes).
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));
        $super = User::factory()->create(['email' => 'equipe@oravel.test']);
        Livewire::actingAs($super)->test(ListDashboardTemplates::class)->assertCanSeeTableRecords([$modelo]);
    }

    public function test_blocos_da_demo_trazem_os_formatos_e_os_numeros_da_historia(): void
    {
        $admin = $this->entrarNaDemo();
        $blocos = collect(DashboardResolver::blocks($this->modelo()->load('blocks'), $admin))->keyBy('key');

        $this->assertCount(13, $blocos);
        $this->assertSame(['kpi', 'kpi', 'kpi', 'kpi', 'map', 'heat', 'ranking', 'donut', 'donut', 'table', 'alerts', 'timeline', 'timeline'], $blocos->pluck('payload.type')->values()->all());

        $this->assertNotEmpty($blocos['ativos_ia.ocorrencias']['payload']['events']);
        $this->assertNotEmpty($blocos['ativos_ia.devolucoes']['payload']['events']);

        $this->assertSame(3, $blocos['ativos_ia.trocar_vender']['payload']['value']);
        $this->assertSame(1, $blocos['ativos_ia.em_revisao']['payload']['value']);
        $this->assertSame(25, $blocos['ativos_ia.localizados']['payload']['value']);
        $this->assertCount(12, $blocos['ativos_ia.custo_12m']['payload']['spark']);
        $this->assertStringStartsWith('R$ ', (string) $blocos['ativos_ia.custo_12m']['payload']['value']);

        $this->assertCount(25, $blocos['ativos_ia.mapa']['payload']['points']);
        $this->assertNotEmpty($blocos['ativos_ia.mapa']['payload']['legend']);

        $calor = $blocos['ativos_ia.mapa_calor']['payload'];
        $this->assertCount(10, $calor['rows']);
        $this->assertCount(12, $calor['columns']);
        $this->assertCount(12, $calor['rows'][0][2]);

        $ranking = $blocos['ativos_ia.ranking_valor']['payload'];
        $this->assertStringContainsString('C-02', $ranking['rows'][0][0]);
        $this->assertSame(91, $ranking['rows'][0][2]);
        $this->assertSame('crit', $ranking['rows'][0][4]);
        $this->assertSame([40, 60], $ranking['marks']);

        $situacao = $blocos['ativos_ia.situacao']['payload'];
        $this->assertSame('31', $situacao['center']); // 25 equipamentos + 6 veiculos da frota
        $this->assertSame(31, array_sum(array_column($situacao['slices'], 1)));
        $this->assertGreaterThan(0, count($blocos['ativos_ia.tipo_manutencao']['payload']['slices']));

        $this->assertCount(6, $blocos['ativos_ia.menos_retorno']['payload']['rows']);
        $this->assertContains('Trocar/vender', array_column($blocos['ativos_ia.alertas']['payload']['items'], 0));
    }

    public function test_inicio_da_demo_renderiza_mapa_mapa_de_calor_aneis_e_ranking(): void
    {
        $this->entrarNaDemo();

        Livewire::test(PainelGestao::class)
            ->assertSee('Onde estão os equipamentos')
            ->assertSee('Mapa de calor do custo por equipamento')
            ->assertSee('Quanto do valor já foi gasto em manutenção')
            ->assertSee('Situação da frota')
            ->assertSee('Para onde vai o dinheiro')
            ->assertSee('Quem dá menos retorno')
            ->assertSee('O que pede atenção')
            ->assertSeeHtml('odb-heat')->assertSeeHtml('odb-rk')->assertSeeHtml('odb-donut')->assertSeeHtml('odb-spark')->assertSeeHtml('odbMapa');
    }

    public function test_sem_as_chaves_no_contrato_nenhum_bloco_aparece_mesmo_atribuido(): void
    {
        $modelo = DashboardInteligenciaDeAtivos::garantirModelo();
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'dia-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        DashboardInteligenciaDeAtivos::atribuirA($tenant, true);

        $this->assertSame([], DashboardResolver::blocks($modelo->load('blocks'), $admin));
        $this->assertTrue(DashboardResolver::visiveisPara($admin, true)->isEmpty(), 'sem bloco visivel o dashboard nem aparece para o cliente');
    }

    public function test_comando_cria_o_modelo_sem_duplicar_e_atribui_so_a_empresa_pedida(): void
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $a = Tenant::create(['name' => 'Cliente A', 'slug' => 'ca-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $b = Tenant::create(['name' => 'Cliente B', 'slug' => 'cb-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);

        $this->artisan('dashboards:inteligencia-ativos')->assertSuccessful();
        $this->artisan('dashboards:inteligencia-ativos', ['--empresa' => $a->slug])->assertSuccessful();

        $this->assertSame(1, DashboardTemplate::withoutGlobalScopes()->whereNull('tenant_id')->where('name', DashboardInteligenciaDeAtivos::NOME)->count());
        $this->assertCount(13, $this->modelo()->blocks);
        $this->assertSame(1, DashboardAssignment::withoutGlobalScopes()->where('dashboard_template_id', $this->modelo()->id)->where('tenant_id', $a->id)->count());
        $this->assertSame(0, DashboardAssignment::withoutGlobalScopes()->where('dashboard_template_id', $this->modelo()->id)->where('tenant_id', $b->id)->count());
    }
}
