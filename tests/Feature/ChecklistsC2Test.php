<?php

namespace Tests\Feature;

use App\Filament\Resources\CkTemplateResource;
use App\Filament\Resources\CkTemplateResource\Pages\CreateCkTemplate;
use App\Filament\Resources\CkTemplateResource\Pages\EditCkTemplate;
use App\Filament\Resources\CkTemplateResource\Pages\ListCkTemplates;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\ChecklistGroup;
use App\Models\CkExecution;
use App\Models\CkItem;
use App\Models\CkSection;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Checklists\ChecklistAplicavel;
use App\Services\Checklists\ChecklistGestao;
use App\Services\Checklists\ChecklistInstalador;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Checklists por grupo de equipamento, C2: CRUD no painel (repeater aninhado), versao, duplicar, restaurar padrao, ativar/desativar,
 * arquivar e autorizacao por permissao. SEGURANCA: so roda em banco com "test" no nome.
 */
class ChecklistsC2Test extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        [$this->tenant, $this->admin] = $this->novoTenant();
        app(CatalogoInstalador::class)->instalar($this->tenant);
        app(ChecklistInstalador::class)->instalar($this->tenant);
        $this->actingAs($this->admin);
    }

    /** @return array{0: Tenant, 1: User} */
    private function novoTenant(bool $comChave = true): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ($comChave ? [CatalogoItem::FEATURE_KEY => true, CkTemplate::FEATURE_KEY => true] : []) + ['tabela_assets' => true]]);
        $t = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'ck2-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);

        return [$t, $this->usuario($t, admin: true)];
    }

    private function usuario(Tenant $t, bool $admin = false, array $permissoes = []): User
    {
        $u = User::create(['name' => 'U', 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();

        if ($admin) {
            $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        }

        foreach ($permissoes as $p) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }

        return $u;
    }

    private function tpl(string $slug): CkTemplate
    {
        return CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', $slug)->firstOrFail();
    }

    private function itensDe(CkTemplate $t)
    {
        return CkItem::withoutGlobalScopes()->whereIn('section_id', CkSection::withoutGlobalScopes()->where('template_id', $t->id)->pluck('id'));
    }

    // ------------------------------------------------------------ autorizacao

    public function test_admin_ve_lista_com_os_8_checklists_e_filtra(): void
    {
        $gerador = $this->tpl('gerador-pre-uso');

        Livewire::test(ListCkTemplates::class)
            ->assertCanSeeTableRecords(CkTemplate::all())
            ->assertCountTableRecords(8)
            ->filterTable('grupo_id', $gerador->grupo_id)->assertCountTableRecords(1)
            ->resetTableFilters()->filterTable('tipo', 'saida')->assertCountTableRecords(2)
            ->resetTableFilters()->filterTable('tipo', 'diario')->assertCountTableRecords(1)
            ->resetTableFilters()->filterTable('origem', 'customizado')->assertCountTableRecords(0)
            ->resetTableFilters()->filterTable('origem', 'padrao')->assertCountTableRecords(8);

        $gerador->update(['ativo' => false]);
        Livewire::test(ListCkTemplates::class)->filterTable('ativo', false)->assertCountTableRecords(1)->resetTableFilters()->filterTable('ativo', true)->assertCountTableRecords(7);
    }

    public function test_so_enxerga_o_proprio_tenant(): void
    {
        [$outro] = $this->novoTenant();
        app(CatalogoInstalador::class)->instalar($outro);
        app(ChecklistInstalador::class)->instalar($outro, 'gerador');
        $idDeles = CkTemplate::withoutGlobalScopes()->where('tenant_id', $outro->id)->value('id');

        Livewire::test(ListCkTemplates::class)->assertCountTableRecords(8)->assertCanNotSeeTableRecords(CkTemplate::withoutGlobalScopes()->where('tenant_id', $outro->id)->get());

        try {
            Livewire::test(EditCkTemplate::class, ['record' => $idDeles]);
            $this->fail('abriu o checklist de outro tenant');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
    }

    public function test_permissoes_leitura_edicao_e_sem_permissao(): void
    {
        $leitor = $this->usuario($this->tenant, permissoes: ['ler_checklist_equipamento']);
        $editor = $this->usuario($this->tenant, permissoes: ['ler_checklist_equipamento', 'criar_checklist_equipamento', 'editar_checklist_equipamento', 'excluir_checklist_equipamento']);
        $tecnico = $this->usuario($this->tenant, permissoes: ['criar_execucao_checklist', 'ler_execucao_checklist']);
        $tpl = $this->tpl('gerador-pre-uso');

        $this->actingAs($leitor);
        $this->assertTrue(CkTemplateResource::canViewAny());
        $this->assertFalse(CkTemplateResource::canCreate());
        $this->assertFalse(CkTemplateResource::canEdit($tpl));
        Livewire::test(EditCkTemplate::class, ['record' => $tpl->getKey()])->assertForbidden();
        Livewire::test(ListCkTemplates::class)->assertCanSeeTableRecords(CkTemplate::all())->assertTableActionHidden('duplicar', $tpl)->assertTableActionHidden('alternarAtivo', $tpl);

        $this->actingAs($editor);
        $this->assertTrue(CkTemplateResource::canCreate());
        $this->assertTrue(CkTemplateResource::canEdit($tpl));
        $this->assertTrue(CkTemplateResource::canDelete($tpl));

        $this->actingAs($tecnico); // tecnico executa, nao edita modelos
        $this->assertFalse(CkTemplateResource::canViewAny());
        $this->assertFalse(CkTemplateResource::canEdit($tpl));
    }

    public function test_sem_a_chave_no_contrato_nem_o_admin_acessa(): void
    {
        [, $adminOff] = $this->novoTenant(false);
        $this->actingAs($adminOff);

        $this->assertFalse(CkTemplateResource::canViewAny());
        $this->assertFalse(CkTemplateResource::canCreate());
        $this->assertFalse(CkTemplateResource::shouldRegisterNavigation());
    }

    // ------------------------------------------------------------ criar e editar

    public function test_cria_checklist_com_secoes_e_itens_aninhados(): void
    {
        $grupo = AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', 'gerador')->firstOrFail();

        Livewire::test(CreateCkTemplate::class)
            ->fillForm([
                'nome' => 'Gerador da obra Norte', 'grupo_id' => $grupo->id, 'tipo' => 'diario', 'periodicidade' => 'por_medidor', 'intervalo_horimetro_km' => 250, 'ativo' => true,
                'secoes' => [
                    ['titulo' => 'Motor', 'itens' => [
                        ['descricao' => 'Nível de óleo', 'tipo_resposta' => 'conforme_nao_conforme_na', 'obrigatorio' => true, 'critico' => true, 'exige_foto_se_nao_conforme' => true, 'ativo' => true],
                        ['descricao' => 'Horímetro', 'tipo_resposta' => 'numerico', 'unidade' => 'h', 'obrigatorio' => true, 'ativo' => true],
                    ]],
                    ['titulo' => 'Elétrica', 'itens' => [['descricao' => 'Aterramento', 'tipo_resposta' => 'conforme_nao_conforme_na', 'obrigatorio' => true, 'ativo' => true]]],
                ],
            ])
            ->call('create')->assertHasNoFormErrors();

        $t = CkTemplate::withoutGlobalScopes()->where('nome', 'Gerador da obra Norte')->firstOrFail();
        $this->assertSame($this->tenant->id, $t->tenant_id);
        $this->assertSame('customizado', $t->origem);
        $this->assertTrue((bool) $t->personalizado);
        $this->assertSame(1, $t->versao);
        $this->assertNull($t->slug_template);
        $this->assertSame(250, $t->intervalo_horimetro_km);

        $secoes = CkSection::withoutGlobalScopes()->where('template_id', $t->id)->orderBy('ordem')->get();
        $this->assertSame(['Motor', 'Elétrica'], $secoes->pluck('titulo')->all());
        $this->assertSame([$this->tenant->id], $secoes->pluck('tenant_id')->unique()->all());
        $itens = $this->itensDe($t)->get();
        $this->assertCount(3, $itens);
        $this->assertSame([$this->tenant->id], $itens->pluck('tenant_id')->unique()->all());
        $this->assertTrue((bool) $itens->firstWhere('descricao', 'Nível de óleo')->critico);
    }

    public function test_salvar_sem_editar_nao_muda_a_versao_e_editar_sobe_e_marca_personalizado(): void
    {
        $tpl = $this->tpl('empilhadeira-pre-uso');
        $this->assertSame(1, $tpl->versao);
        $this->assertFalse((bool) $tpl->personalizado);

        $c = Livewire::test(EditCkTemplate::class, ['record' => $tpl->getKey()]);
        $c->call('save')->assertHasNoFormErrors();
        $this->assertSame(1, $tpl->fresh()->versao, 'salvar sem editar nao gasta versao');
        $this->assertFalse((bool) $tpl->fresh()->personalizado);

        $secoes = $c->get('data.secoes');
        $ks = array_key_first($secoes);
        $ki = array_key_first($secoes[$ks]['itens']);
        $c->set("data.secoes.{$ks}.itens.{$ki}.descricao", 'Texto reescrito pela empresa')->call('save')->assertHasNoFormErrors();

        $tpl = $tpl->fresh();
        $this->assertSame(2, $tpl->versao);
        $this->assertTrue((bool) $tpl->personalizado);
        $this->assertSame('padrao', $tpl->origem);
        $this->assertTrue($this->itensDe($tpl)->where('descricao', 'Texto reescrito pela empresa')->exists());
    }

    public function test_editar_o_template_nao_altera_execucao_antiga(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');
        $ativo = Asset::factory()->create(['tenant_id' => $this->tenant->id, 'status' => Asset::STATUS_DISPONIVEL]);
        $exec = CkExecution::create(['asset_id' => $ativo->id, 'template_id' => $tpl->id, 'template_nome' => $tpl->nome, 'versao_template' => $tpl->versao, 'executor_id' => $this->admin->id, 'iniciado_em' => now()]);

        $c = Livewire::test(EditCkTemplate::class, ['record' => $tpl->getKey()]);
        $c->fillForm(['nome' => 'Outro nome do checklist'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Outro nome do checklist', $tpl->fresh()->nome);
        $this->assertSame(2, $tpl->fresh()->versao);
        $this->assertSame(1, $exec->fresh()->versao_template);
        $this->assertStringNotContainsString('Outro nome', $exec->fresh()->template_nome);
    }

    public function test_reordenar_secoes_persiste_a_ordem(): void
    {
        $tpl = $this->tpl('guindaste-pre-uso');
        $antes = CkSection::withoutGlobalScopes()->where('template_id', $tpl->id)->orderBy('ordem')->pluck('titulo')->all();

        $c = Livewire::test(EditCkTemplate::class, ['record' => $tpl->getKey()]);
        $c->set('data.secoes', array_reverse($c->get('data.secoes'), true))->call('save')->assertHasNoFormErrors();

        $depois = CkSection::withoutGlobalScopes()->where('template_id', $tpl->id)->orderBy('ordem')->pluck('titulo')->all();
        $this->assertSame(array_reverse($antes), $depois);
        $this->assertSame(2, $tpl->fresh()->versao, 'mudar a ordem tambem e mudanca de conteudo');
    }

    public function test_remover_item_arquiva_e_nao_apaga_execucao_antiga(): void
    {
        $tpl = $this->tpl('compressor-diario');
        $total = $this->itensDe($tpl)->count();

        $c = Livewire::test(EditCkTemplate::class, ['record' => $tpl->getKey()]);
        $secoes = $c->get('data.secoes');
        $ks = array_key_first($secoes);
        $itens = $secoes[$ks]['itens'];
        unset($itens[array_key_first($itens)]);
        $c->set("data.secoes.{$ks}.itens", $itens)->call('save')->assertHasNoFormErrors();

        $this->assertSame($total - 1, $this->itensDe($tpl)->whereNull('deleted_at')->count());
        $this->assertSame($total, $this->itensDe($tpl)->count(), 'o item fica arquivado (soft delete), nao some do banco');
    }

    // ------------------------------------------------------------ duplicar, restaurar, ativar, arquivar

    public function test_duplicar_cria_copia_independente_e_customizada(): void
    {
        $orig = $this->tpl('munk-pre-uso');
        $nItens = $this->itensDe($orig)->whereNull('deleted_at')->count();

        Livewire::test(ListCkTemplates::class)->callTableAction('duplicar', $orig, data: ['nome' => 'Munk da frota 2'])->assertHasNoTableActionErrors();

        $copia = CkTemplate::withoutGlobalScopes()->where('nome', 'Munk da frota 2')->firstOrFail();
        $this->assertNotSame($orig->id, $copia->id);
        $this->assertSame('customizado', $copia->origem);
        $this->assertNull($copia->slug_template);
        $this->assertSame(1, $copia->versao);
        $this->assertSame($orig->grupo_id, $copia->grupo_id);
        $this->assertSame($nItens, $this->itensDe($copia)->count());
        $this->assertSame([], array_intersect($this->itensDe($orig)->pluck('id')->all(), $this->itensDe($copia)->pluck('id')->all()), 'itens novos, nao os mesmos');
        $this->assertSame(
            $this->itensDe($orig)->whereNotNull('catalogo_requisito_id')->count(),
            $this->itensDe($copia)->whereNotNull('catalogo_requisito_id')->count(),
            'o vinculo com o requisito do catalogo e mantido'
        );

        $this->itensDe($copia)->first()->update(['descricao' => 'so na copia']);
        $this->assertFalse($this->itensDe($orig)->where('descricao', 'so na copia')->exists(), 'editar a copia nao muda o original');
    }

    public function test_restaurar_padrao_exige_justificativa_e_volta_ao_yaml(): void
    {
        $tpl = $this->tpl('veiculo-leve-saida');
        $nPadrao = $this->itensDe($tpl)->count();

        $tpl->update(['nome' => 'Meu roteiro', 'personalizado' => true]);
        $this->itensDe($tpl)->limit(5)->get()->each->delete();
        $this->itensDe($tpl)->first()->update(['descricao' => 'texto da empresa']);

        $c = Livewire::test(ListCkTemplates::class);
        $c->callTableAction('restaurarPadrao', $tpl, data: [])->assertHasTableActionErrors(['justificativa' => 'required']);
        $this->assertSame('Meu roteiro', $tpl->fresh()->nome, 'sem justificativa nada muda');

        $c->callTableAction('restaurarPadrao', $tpl, data: ['justificativa' => 'Voltar ao padrão da Oravel'])->assertHasNoTableActionErrors();

        $tpl = $tpl->fresh();
        $this->assertSame('Checklist de saída do veículo leve', $tpl->nome);
        $this->assertFalse((bool) $tpl->personalizado);
        $this->assertSame('padrao', $tpl->origem);
        $this->assertSame(2, $tpl->versao);
        $this->assertSame($nPadrao, $this->itensDe($tpl)->count());
        $this->assertFalse($this->itensDe($tpl)->where('descricao', 'texto da empresa')->exists());

        $log = Activity::where('log_name', 'checklist_equipamento')->where('event', 'padrao_restaurado')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('Voltar ao padrão da Oravel', $log->properties['justificativa']);
    }

    public function test_restaurar_padrao_nao_existe_para_checklist_customizado(): void
    {
        $copia = app(ChecklistGestao::class)->duplicar($this->tpl('gerador-pre-uso'), 'Meu gerador');

        Livewire::test(ListCkTemplates::class)->assertTableActionHidden('restaurarPadrao', $copia)->assertTableActionVisible('restaurarPadrao', $this->tpl('gerador-pre-uso'));
    }

    public function test_restaurar_padrao_devolve_checklist_arquivado_e_inativo(): void
    {
        $tpl = $this->tpl('caminhao-saida');
        $tpl->update(['ativo' => false]);

        app(ChecklistGestao::class)->restaurarPadrao($tpl, 'Reativar com o padrão');

        $this->assertTrue((bool) $tpl->fresh()->ativo);
    }

    public function test_ativar_e_desativar(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');

        Livewire::test(ListCkTemplates::class)->callTableAction('alternarAtivo', $tpl);
        $this->assertFalse((bool) $tpl->fresh()->ativo);

        Livewire::test(ListCkTemplates::class)->callTableAction('alternarAtivo', $tpl);
        $this->assertTrue((bool) $tpl->fresh()->ativo);
    }

    public function test_arquivar_e_restaurar_checklist(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');

        Livewire::test(ListCkTemplates::class)->callTableAction('delete', $tpl);
        $this->assertTrue(CkTemplate::withoutGlobalScopes()->withTrashed()->find($tpl->id)->trashed());
        $this->assertCount(7, CkTemplate::all());

        $arquivado = CkTemplate::withoutGlobalScopes()->withTrashed()->find($tpl->id); // o Filament avalia a visibilidade no objeto recebido
        Livewire::test(ListCkTemplates::class)->filterTable('trashed', true)->assertTableActionVisible('restaurar', $arquivado)->assertTableActionHidden('alternarAtivo', $arquivado)->callTableAction('restaurar', $arquivado);
        $this->assertFalse(CkTemplate::withoutGlobalScopes()->withTrashed()->find($tpl->id)->trashed());
    }

    public function test_botao_instalar_padrao_so_adiciona_o_que_falta(): void
    {
        $this->tpl('gerador-pre-uso')->forceDelete();

        Livewire::test(ListCkTemplates::class)->callAction('instalar');

        $this->assertSame(8, CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    // ------------------------------------------------------------ ativacao pelo grupo de checklist (o que vale na OS)

    private function grupoChecklist(string $nome, ?Tenant $t = null): ChecklistGroup
    {
        return ChecklistGroup::withoutGlobalScopes()->create(['tenant_id' => ($t ?? $this->tenant)->id, 'name' => $nome]);
    }

    private function ativoDoGrupo(string $grupoEquipamento, ?ChecklistGroup $gc = null): Asset
    {
        $cat = AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', $grupoEquipamento)->firstOrFail();

        return Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => $cat->id, 'checklist_group_id' => $gc?->id, 'status' => Asset::STATUS_DISPONIVEL]);
    }

    public function test_template_ligado_a_um_grupo_de_checklist_vale_so_para_os_ativos_desse_grupo(): void
    {
        $g1 = $this->grupoChecklist('Geradores de obra');
        $g2 = $this->grupoChecklist('Geradores de evento');
        $tpl = $this->tpl('gerador-pre-uso');
        $tpl->update(['checklist_group_id' => $g1->id]);
        $svc = app(ChecklistAplicavel::class);

        $this->assertSame([$tpl->id], $svc->paraAtivo($this->ativoDoGrupo('gerador', $g1))->pluck('id')->all(), 'ativo do grupo de checklist G1 recebe o checklist');
        $this->assertSame([], $svc->paraAtivo($this->ativoDoGrupo('gerador', $g2))->pluck('id')->all(), 'ativo de outro grupo de checklist nao recebe, mesmo sendo gerador');
        $this->assertSame([], $svc->paraAtivo($this->ativoDoGrupo('gerador'))->pluck('id')->all(), 'sem grupo de checklist, template com grupo de checklist nao vale pelo grupo de equipamento');
    }

    public function test_sem_grupo_de_checklist_o_template_vale_para_o_grupo_de_equipamento(): void
    {
        $svc = app(ChecklistAplicavel::class);
        $tpl = $this->tpl('empilhadeira-pre-uso');

        $this->assertSame([$tpl->id], $svc->paraAtivo($this->ativoDoGrupo('empilhadeira'))->pluck('id')->all());
        $this->assertSame([$tpl->id], $svc->paraAtivo($this->ativoDoGrupo('empilhadeira', $this->grupoChecklist('Qualquer')))->pluck('id')->all(), 'ativo com outro grupo de checklist ainda recebe o padrao do equipamento');
        $this->assertSame([], $svc->paraAtivo(Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => null, 'checklist_group_id' => null, 'status' => Asset::STATUS_DISPONIVEL]))->pluck('id')->all(), 'ativo sem nenhum grupo nao recebe nada');
    }

    public function test_inativo_arquivado_e_outro_tenant_nao_valem_e_o_tipo_filtra(): void
    {
        $svc = app(ChecklistAplicavel::class);
        $ativo = $this->ativoDoGrupo('caminhao');
        $tpl = $this->tpl('caminhao-saida');
        $this->assertCount(1, $svc->paraAtivo($ativo));
        $this->assertCount(1, $svc->paraAtivo($ativo, ['saida', 'entrada']));
        $this->assertCount(0, $svc->paraAtivo($ativo, ['diario']));

        $tpl->update(['ativo' => false]);
        $this->assertCount(0, $svc->paraAtivo($ativo), 'inativo nao vale');
        $tpl->update(['ativo' => true]);
        $tpl->delete();
        $this->assertCount(0, $svc->paraAtivo($ativo), 'arquivado nao vale');
        $tpl->restore();

        [$outro] = $this->novoTenant();
        app(CatalogoInstalador::class)->instalar($outro);
        app(ChecklistInstalador::class)->instalar($outro, 'caminhao');
        $this->assertSame([$tpl->id], $svc->paraAtivo($ativo)->pluck('id')->all(), 'checklist de outro tenant nunca entra');
    }

    public function test_a_os_recebe_os_checklists_do_grupo_de_checklist_do_ativo(): void
    {
        $g1 = $this->grupoChecklist('Munks da frota A');
        $tpl = $this->tpl('munk-pre-uso');
        $tpl->update(['checklist_group_id' => $g1->id]);
        $ativo = $this->ativoDoGrupo('munk', $g1);
        $os = MaintenanceOrder::create(['tenant_id' => $this->tenant->id, 'asset_id' => $ativo->id, 'description' => 'x', 'maintenance_type' => 'Corretiva', 'status' => 'Pendente', 'priority' => 'alta']);

        $this->assertSame([$tpl->id], app(ChecklistAplicavel::class)->paraOrdemServico($os)->pluck('id')->all());
    }

    public function test_formulario_liga_o_checklist_a_um_grupo_de_checklist_e_so_oferece_os_da_empresa(): void
    {
        [$outro] = $this->novoTenant();
        $deles = $this->grupoChecklist('Grupo de outra empresa', $outro);
        $meu = $this->grupoChecklist('Meu grupo');
        $tpl = $this->tpl('gerador-pre-uso');

        $c = Livewire::test(EditCkTemplate::class, ['record' => $tpl->getKey()]);
        $c->assertFormFieldExists('checklist_group_id', fn ($campo) => array_keys($campo->getOptions()) === [$meu->id] && $campo->getLabel() === 'Grupo de checklist (ativa na OS)');
        $c->fillForm(['checklist_group_id' => $meu->id])->call('save')->assertHasNoFormErrors();

        $tpl = $tpl->fresh();
        $this->assertSame($meu->id, $tpl->checklist_group_id);
        $this->assertSame(2, $tpl->versao, 'mudar o grupo de checklist muda onde o checklist vale, entao e nova versao');
        $this->assertNotSame($deles->id, $tpl->checklist_group_id);
    }

    public function test_duplicar_mantem_o_grupo_de_checklist(): void
    {
        $g = $this->grupoChecklist('Grupo X');
        $tpl = $this->tpl('compressor-diario');
        $tpl->update(['checklist_group_id' => $g->id]);

        $copia = app(ChecklistGestao::class)->duplicar($tpl, 'Compressor X');

        $this->assertSame($g->id, $copia->checklist_group_id);
    }

    public function test_restaurar_padrao_nao_desfaz_a_ligacao_com_o_grupo_de_checklist(): void
    {
        $g = $this->grupoChecklist('Grupo Y');
        $tpl = $this->tpl('guindaste-pre-uso');
        $tpl->update(['checklist_group_id' => $g->id]);

        app(ChecklistGestao::class)->restaurarPadrao($tpl, 'Voltar ao padrão do texto');

        $this->assertSame($g->id, $tpl->fresh()->checklist_group_id, 'a ligacao e da empresa, nao do padrao');
    }
}
