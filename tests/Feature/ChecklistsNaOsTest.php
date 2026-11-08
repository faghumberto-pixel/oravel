<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource\Pages\CreateMaintenanceOrder;
use App\Filament\Resources\MaintenanceOrderResource\Pages\EditMaintenanceOrder;
use App\Livewire\ChecklistExecucaoMobile;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\ChecklistGroup;
use App\Models\CkExecution;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Checklists\ChecklistGestao;
use App\Services\Checklists\ChecklistInstalador;
use App\Services\Checklists\ChecklistsDaOs;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * O checklist do grupo aparece ao abrir a OS (painel e app do colaborador) e o link abre a execucao ja no modelo certo.
 * Corrige a C3, que entregou a regra (ChecklistAplicavel) mas nao ligou a tela da OS. SEGURANCA: so roda em banco com "test" no nome.
 */
class ChecklistsNaOsTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        [$this->tenant, $this->admin] = $this->novoTenant(true);
        app(CatalogoInstalador::class)->instalar($this->tenant);
        app(ChecklistInstalador::class)->instalar($this->tenant);
        $this->actingAs($this->admin);
    }

    /** @return array{0: Tenant, 1: User} */
    private function novoTenant(bool $comChecklists): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ($comChecklists ? [CatalogoItem::FEATURE_KEY => true, CkTemplate::FEATURE_KEY => true, 'tabela_ck_execucoes' => true] : [])
                + ['tabela_assets' => true, 'tabela_maintenance_orders' => true, 'modulo_app_colaborador' => true]]);
        $t = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'ckos-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);

        return [$t, $this->usuario($t, true)];
    }

    private function usuario(Tenant $t, bool $admin = false, array $permissoes = []): User
    {
        $u = User::create(['name' => 'U '.uniqid(), 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();

        if ($admin) {
            $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        }

        foreach ($permissoes as $p) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }

        return $u->refresh();
    }

    private function ativo(string $grupo = 'gerador', ?ChecklistGroup $gc = null): Asset
    {
        $cat = AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', $grupo)->firstOrFail();

        return Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => $cat->id, 'checklist_group_id' => $gc?->id, 'status' => Asset::STATUS_DISPONIVEL]);
    }

    private function os(Asset $a): MaintenanceOrder
    {
        return MaintenanceOrder::create(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'description' => 'x', 'maintenance_type' => 'Preventiva', 'status' => 'Pendente', 'priority' => 'media']);
    }

    private function tpl(string $slug): CkTemplate
    {
        return CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', $slug)->firstOrFail();
    }

    public function test_ao_abrir_a_os_aparece_o_checklist_do_grupo_com_o_link_para_executar(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);
        $tpl = $this->tpl('gerador-pre-uso');

        Livewire::test(EditMaintenanceOrder::class, ['record' => $os->id])
            ->assertSee('Checklists do equipamento')
            ->assertSee('Checklist de pré-uso do gerador')
            ->assertSee('19 itens')->assertSee('8 críticos')->assertSee('grupo de equipamento: Gerador')
            ->assertSee('Executar')
            ->assertSee(route('ck.execucao.mobile', ['assetId' => $a->id, 'checklist' => $tpl->id]), false);
    }

    public function test_a_origem_do_vinculo_diz_grupo_de_checklist_quando_e_por_ele(): void
    {
        $gc = ChecklistGroup::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Geradores de obra']);
        $this->tpl('gerador-pre-uso')->update(['checklist_group_id' => $gc->id]);
        $a = $this->ativo('gerador', $gc);
        $semGrupo = $this->ativo('gerador'); // outro gerador, sem o grupo de checklist

        Livewire::test(EditMaintenanceOrder::class, ['record' => $this->os($a)->id])->assertSee('grupo de checklist: Geradores de obra');
        Livewire::test(EditMaintenanceOrder::class, ['record' => $this->os($semGrupo)->id])->assertSee('Nenhum checklist por grupo se aplica a este equipamento')->assertDontSee('Checklist de pré-uso do gerador');
    }

    public function test_os_de_ativo_sem_checklist_aplicavel_explica_como_ligar(): void
    {
        $semGrupo = Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => null, 'checklist_group_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);

        Livewire::test(EditMaintenanceOrder::class, ['record' => $this->os($semGrupo)->id])
            ->assertSee('Checklists do equipamento')->assertSee('Nenhum checklist por grupo se aplica a este equipamento')->assertSee('Checklists de equipamento');
    }

    public function test_mostra_os_ultimos_checklists_feitos_no_equipamento(): void
    {
        $a = $this->ativo('gerador');
        CkExecution::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id, 'asset_id' => $a->id, 'template_nome' => 'Checklist de pré-uso do gerador', 'versao_template' => 1, 'executor_id' => $this->admin->id,
            'status' => CkExecution::STATUS_REPROVADO, 'iniciado_em' => now()->subHour(), 'finalizado_em' => now(),
        ]);

        Livewire::test(EditMaintenanceOrder::class, ['record' => $this->os($a)->id])->assertSee('Últimos checklists deste equipamento')->assertSee('Reprovado');
    }

    public function test_a_secao_some_sem_permissao_e_sem_a_chave_do_contrato(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);

        $this->actingAs($this->usuario($this->tenant, permissoes: ['ler_ordem_servico']));
        $this->assertNull(app(ChecklistsDaOs::class)->paraOrdemServico($os), 'sem permissao de checklist: nada para mostrar');

        [$off, $adminOff] = $this->novoTenant(false);
        $this->actingAs($adminOff);
        $semChave = Asset::factory()->create(['tenant_id' => $off->id, 'status' => Asset::STATUS_DISPONIVEL]);
        $this->assertNull(app(ChecklistsDaOs::class)->paraAtivo($semChave->id), 'sem a chave no contrato a secao nao existe');
    }

    public function test_so_com_permissao_de_ver_sem_executar_nao_mostra_o_botao(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);
        $this->actingAs($this->usuario($this->tenant, permissoes: ['ler_execucao_checklist']));

        $dados = app(ChecklistsDaOs::class)->paraOrdemServico($os);

        $this->assertNotNull($dados);
        $this->assertFalse($dados['pode_executar']);
        $this->assertStringNotContainsString('ck.execucao', view('filament.forms.checklists-da-os', ['dados' => $dados])->render().'');
        $this->assertStringNotContainsString('Executar</a>', view('filament.forms.checklists-da-os', ['dados' => $dados])->render());
    }

    public function test_o_app_do_colaborador_mostra_o_checklist_na_os(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);
        $tpl = $this->tpl('gerador-pre-uso');

        $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($this->admin)->get('/colaborador/os/'.$os->id)->assertOk()
            ->assertSee('Checklists do equipamento')->assertSee('Checklist de pré-uso do gerador')->assertSee('EXECUTAR CHECKLIST')
            ->assertSee(route('ck.execucao.mobile', ['assetId' => $a->id, 'checklist' => $tpl->id]), false);
    }

    public function test_o_app_do_colaborador_nao_mostra_a_secao_quando_nao_ha_checklist(): void
    {
        $semGrupo = Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => null, 'checklist_group_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);

        $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($this->admin)->get('/colaborador/os/'.$this->os($semGrupo)->id)->assertOk()->assertDontSee('EXECUTAR CHECKLIST');
    }

    public function test_o_link_da_os_abre_a_execucao_ja_no_checklist_escolhido(): void
    {
        $a = $this->ativo('gerador');
        $primeiro = $this->tpl('gerador-pre-uso');
        $segundo = app(ChecklistGestao::class)->duplicar($primeiro, 'Gerador, roteiro de fim de obra');

        // (o withQueryParams do Livewire fica gravado nas chamadas seguintes: o caso sem link vem primeiro)
        $semLink = Livewire::test(ChecklistExecucaoMobile::class, ['assetId' => $a->id]);
        $this->assertNull($semLink->get('templateId'), 'sem link e com dois modelos, a pessoa escolhe');

        $c = Livewire::withQueryParams(['checklist' => $segundo->id])->test(ChecklistExecucaoMobile::class, ['assetId' => $a->id]);
        $this->assertSame($segundo->id, $c->get('templateId'), 'com dois modelos, o que veio no link ja vem escolhido');

        $invalido = Livewire::withQueryParams(['checklist' => 'nao-existe'])->test(ChecklistExecucaoMobile::class, ['assetId' => $a->id]);
        $this->assertNull($invalido->get('templateId'), 'id que nao se aplica ao ativo e ignorado');

        [$outroTenant] = $this->novoTenant(true);
        app(CatalogoInstalador::class)->instalar($outroTenant);
        app(ChecklistInstalador::class)->instalar($outroTenant, 'gerador');
        $deles = CkTemplate::withoutGlobalScopes()->where('tenant_id', $outroTenant->id)->value('id');
        $alheio = Livewire::withQueryParams(['checklist' => $deles])->test(ChecklistExecucaoMobile::class, ['assetId' => $a->id]);
        $this->assertNull($alheio->get('templateId'), 'checklist de outro tenant nunca e escolhido por link');
    }

    public function test_criar_uma_os_mostra_o_checklist_assim_que_o_ativo_e_escolhido(): void
    {
        $a = $this->ativo('gerador');

        Livewire::test(CreateMaintenanceOrder::class)->fillForm(['asset_id' => $a->id])->assertSee('Checklist de pré-uso do gerador');
    }
}
