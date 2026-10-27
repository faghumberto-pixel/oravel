<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Personalizacao\DashboardTemplateService;
use App\Filament\Central\Resources\DashboardTemplateResource;
use App\Filament\Central\Resources\DashboardTemplateResource\Pages\CreateDashboardTemplate;
use App\Filament\Central\Resources\DashboardTemplateResource\Pages\EditDashboardTemplate;
use App\Filament\Central\Resources\DashboardTemplateResource\Pages\ListDashboardTemplates;
use App\Filament\Central\Resources\DashboardTemplateResource\RelationManagers\AssignmentsRelationManager;
use App\Filament\Pages\DashboardsDaEmpresa;
use App\Models\DashboardAssignment;
use App\Models\DashboardTemplate;
use App\Models\DashboardTemplateBlock;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardsCentralTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_personalizados' => true]);
    }

    private function superAdmin(): User
    {
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => null, 'is_approved' => true]);
        config(['oravel.super_admins' => [$super->email]]);
        $this->actingAs($super);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        return $super;
    }

    private function empresa(string $nome = 'Empresa'): Tenant
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets' => true]]);

        return Tenant::create(['name' => $nome.' '.uniqid(), 'slug' => 'ct-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function modelo(string $nome = 'Direção', ?Tenant $dono = null): DashboardTemplate
    {
        $m = DashboardTemplate::create(['name' => $nome, 'tenant_id' => $dono?->id, 'profile' => 'direcao', 'description' => 'desc']);
        DashboardTemplateBlock::create(['dashboard_template_id' => $m->id, 'block_key' => 'ativos.total', 'position' => 0, 'config' => [], 'ficha_dor' => 'Não sabe quanto tem parado']);
        DashboardTemplateBlock::create(['dashboard_template_id' => $m->id, 'block_key' => 'manutencao.no_prazo', 'position' => 1, 'config' => ['meta' => 80], 'ficha_meta' => '90%', 'ficha_revisao_em' => '2027-02-01']);

        return $m;
    }

    // ── acesso ──

    public function test_so_super_admin_com_a_flag_ligada_acessa(): void
    {
        $plan = Plan::create(['name' => 'P', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $comum = User::create(['name' => 'Comum', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $comum->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($comum);
        $this->assertFalse(DashboardTemplateResource::canAccess());

        $this->superAdmin();
        $this->assertTrue(DashboardTemplateResource::canAccess());

        config(['oravel.dashboards_personalizados' => false]);
        $this->assertFalse(DashboardTemplateResource::canAccess());
        $this->assertFalse(DashboardTemplateResource::shouldRegisterNavigation());
    }

    // ── criar e editar ──

    public function test_cria_modelo_da_plataforma_com_blocos_ordenados_ajustes_e_ficha(): void
    {
        $this->superAdmin();

        Livewire::test(CreateDashboardTemplate::class)
            ->fillForm([
                'name' => 'Painel do Estoque',
                'profile' => 'estoque',
                'is_active' => true,
                'blocks' => [
                    ['block_key' => 'materiais.valor_em_estoque', 'config' => [], 'ficha_dor' => 'Compra sem ver o saldo', 'ficha_indicador' => 'Itens abaixo do mínimo', 'ficha_meta' => 'Zerar em 60 dias', 'ficha_revisao_em' => '2027-01-15'],
                    ['block_key' => 'manutencao.no_prazo', 'config' => ['meta' => '85']],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $modelo = DashboardTemplate::where('name', 'Painel do Estoque')->firstOrFail();
        $blocos = $modelo->blocks;

        $this->assertNull($modelo->tenant_id);
        $this->assertSame(['materiais.valor_em_estoque', 'manutencao.no_prazo'], $blocos->pluck('block_key')->all());
        $this->assertSame($blocos->pluck('position')->sort()->values()->all(), $blocos->pluck('position')->all(), 'a ordem do formulário vira a coluna position');
        $this->assertCount(2, $blocos->pluck('position')->unique());
        $this->assertSame('Compra sem ver o saldo', $blocos[0]->ficha_dor);
        $this->assertSame('2027-01-15', $blocos[0]->ficha_revisao_em->toDateString());
        $this->assertEquals(['meta' => '85'], $blocos[1]->config);
    }

    public function test_ajuste_que_o_bloco_nao_tem_e_recusado(): void
    {
        $this->superAdmin();

        Livewire::test(CreateDashboardTemplate::class)
            ->fillForm([
                'name' => 'Inválido',
                'blocks' => [['block_key' => 'manutencao.no_prazo', 'config' => ['inexistente' => '1']]],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertNull(DashboardTemplate::where('name', 'Inválido')->first());
    }

    public function test_ajuste_numerico_com_texto_e_recusado(): void
    {
        $this->superAdmin();

        Livewire::test(CreateDashboardTemplate::class)
            ->fillForm([
                'name' => 'Texto no número',
                'blocks' => [['block_key' => 'manutencao.no_prazo', 'config' => ['meta' => 'alta']]],
            ])
            ->call('create')
            ->assertHasFormErrors();
    }

    public function test_dashboard_exclusivo_criado_ja_nasce_atribuido_a_empresa(): void
    {
        $this->superAdmin();
        $empresa = $this->empresa();

        Livewire::test(CreateDashboardTemplate::class)
            ->fillForm(['name' => 'Só da empresa', 'tenant_id' => $empresa->id, 'blocks' => [['block_key' => 'ativos.total', 'config' => []]]])
            ->call('create')
            ->assertHasNoFormErrors();

        $modelo = DashboardTemplate::where('name', 'Só da empresa')->firstOrFail();

        $this->assertSame($empresa->id, $modelo->tenant_id);
        $this->assertSame([$empresa->id], $modelo->assignments()->pluck('tenant_id')->all());
    }

    public function test_edicao_mantem_a_empresa_dona_do_exclusivo(): void
    {
        $this->superAdmin();
        $dona = $this->empresa('Dona');
        $outra = $this->empresa('Outra');
        $modelo = $this->modelo('Exclusivo', $dona);

        Livewire::test(EditDashboardTemplate::class, ['record' => $modelo->id])
            ->fillForm(['name' => 'Exclusivo renomeado', 'tenant_id' => $outra->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $modelo->refresh();
        $this->assertSame('Exclusivo renomeado', $modelo->name);
        $this->assertSame($dona->id, $modelo->tenant_id);
    }

    // ── duplicar, exclusivo e atribuir ──

    public function test_duplicar_copia_blocos_ajustes_e_ficha_sem_mexer_no_original(): void
    {
        $original = $this->modelo('Original');

        $copia = DashboardTemplateService::duplicar($original);

        $this->assertNotSame($original->id, $copia->id);
        $this->assertSame('Original (cópia)', $copia->name);
        $this->assertSame($original->id, $copia->source_template_id);
        $this->assertSame(['ativos.total', 'manutencao.no_prazo'], $copia->blocks->pluck('block_key')->all());
        $this->assertEquals(['meta' => 80], $copia->blocks[1]->config);
        $this->assertSame('Não sabe quanto tem parado', $copia->blocks[0]->ficha_dor);
        $this->assertCount(2, $original->fresh()->blocks);
    }

    public function test_exclusivo_para_empresa_copia_atribui_e_deixa_o_modelo_intacto(): void
    {
        $empresa = $this->empresa();
        $modelo = $this->modelo('Modelo');

        $exclusivo = DashboardTemplateService::exclusivoPara($modelo, $empresa);

        $this->assertSame($empresa->id, $exclusivo->tenant_id);
        $this->assertSame($modelo->id, $exclusivo->source_template_id);
        $this->assertCount(2, $exclusivo->blocks);
        $this->assertSame([$empresa->id], $exclusivo->assignments()->pluck('tenant_id')->all());
        $this->assertNull($modelo->fresh()->tenant_id);
        $this->assertCount(0, $modelo->assignments);
    }

    public function test_so_modelo_da_plataforma_vira_exclusivo(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DashboardTemplateService::exclusivoPara($this->modelo('Já exclusivo', $this->empresa()), $this->empresa());
    }

    public function test_atribuir_a_varias_empresas_nao_duplica(): void
    {
        $a = $this->empresa();
        $b = $this->empresa();
        $modelo = $this->modelo();

        $this->assertSame(2, DashboardTemplateService::atribuir($modelo, [$a->id, $b->id]));
        $this->assertSame(1, DashboardTemplateService::atribuir($modelo, [$a->id, $this->empresa()->id]));
        $this->assertSame(3, DashboardAssignment::withoutGlobalScopes()->where('dashboard_template_id', $modelo->id)->count());
    }

    public function test_exclusivo_nao_pode_ser_atribuido_a_outra_empresa(): void
    {
        $dona = $this->empresa();
        $modelo = $this->modelo('Exclusivo', $dona);

        $this->expectException(InvalidArgumentException::class);

        DashboardTemplateService::atribuir($modelo, [$dona->id, $this->empresa()->id]);
    }

    // ── telas ──

    public function test_lista_mostra_tipo_blocos_e_empresas_e_duplica_pela_acao(): void
    {
        $this->superAdmin();
        $empresa = $this->empresa('Exclusiva');
        $plataforma = $this->modelo('Painel Plataforma');
        $this->modelo('Painel Exclusivo', $empresa);
        DashboardTemplateService::atribuir($plataforma, [$this->empresa()->id]);

        Livewire::test(ListDashboardTemplates::class)
            ->assertSee('Painel Plataforma')
            ->assertSee('Painel Exclusivo')
            ->assertSee($empresa->name)
            ->assertSee('Modelo da plataforma')
            ->callTableAction('duplicar', $plataforma);

        $this->assertNotNull(DashboardTemplate::where('name', 'Painel Plataforma (cópia)')->first());
    }

    public function test_acao_exclusivo_pela_lista(): void
    {
        $this->superAdmin();
        $empresa = $this->empresa();
        $modelo = $this->modelo('Base');

        Livewire::test(ListDashboardTemplates::class)
            ->callTableAction('exclusivo', $modelo, data: ['tenant_id' => $empresa->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame(1, DashboardTemplate::where('tenant_id', $empresa->id)->count());
    }

    public function test_acao_atribuir_pela_lista(): void
    {
        $this->superAdmin();
        $a = $this->empresa();
        $b = $this->empresa();
        $modelo = $this->modelo();

        Livewire::test(ListDashboardTemplates::class)
            ->callTableAction('atribuir', $modelo, data: ['tenant_ids' => [$a->id, $b->id]])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $modelo->assignments()->count());
    }

    public function test_gerenciador_de_atribuicoes_lista_e_remove(): void
    {
        $this->superAdmin();
        $a = $this->empresa('Atribuida');
        $modelo = $this->modelo();
        DashboardTemplateService::atribuir($modelo, [$a->id]);

        $rm = Livewire::test(AssignmentsRelationManager::class, ['ownerRecord' => $modelo, 'pageClass' => EditDashboardTemplate::class])
            ->assertSee($a->name);

        $rm->callTableAction('delete', $modelo->assignments()->first());

        $this->assertSame(0, $modelo->assignments()->count());
    }

    public function test_empresa_atribuida_enxerga_o_dashboard_montado_na_central(): void
    {
        $this->superAdmin();
        $plan = Plan::create(['name' => 'P', 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets' => true]]);
        $empresa = Tenant::create(['name' => 'Cliente '.uniqid(), 'slug' => 'cl-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $empresa->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $empresa->id]));
        $modelo = $this->modelo('Direção');
        DashboardTemplateService::atribuir($modelo, [$empresa->id]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(DashboardsDaEmpresa::canAccess());
        Livewire::test(DashboardsDaEmpresa::class)->assertSee('Ativos cadastrados');
    }
}
