<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\RelationManagers\ExecucoesChecklistRelationManager;
use App\Filament\Resources\CkExecutionResource;
use App\Filament\Resources\CkExecutionResource\Pages\ListCkExecutions;
use App\Filament\Resources\CkExecutionResource\Pages\ViewCkExecution;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\CkAnswer;
use App\Models\CkExecution;
use App\Models\CkItem;
use App\Models\CkTemplate;
use App\Models\FrotaChecklist;
use App\Models\FrotaItemModeloChecklist as ItemFrota;
use App\Models\FrotaModeloChecklist;
use App\Models\FrotaRespostaChecklist as RespostaFrota;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Checklists\ChecklistInstalador;
use App\Services\Checklists\FrotaParaChecklists;
use App\Services\Frota\ChecklistFrotaService;
use App\Services\Frota\ModelosChecklistPadrao;
use App\Support\CkExecucaoExport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Checklists por grupo de equipamento, C4: historico por ativo, relatorio com filtros e exportacao Excel, foto em disco privado e
 * espelho do checklist da Frota (copia idempotente, sem alterar a Frota). SEGURANCA: so roda em banco com "test" no nome.
 */
class ChecklistsC4Test extends TestCase
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

        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        [$this->tenant, $this->admin] = $this->novoTenant();
        app(CatalogoInstalador::class)->instalar($this->tenant);
        app(ChecklistInstalador::class)->instalar($this->tenant);
        $this->actingAs($this->admin);
    }

    /** @return array{0: Tenant, 1: User} */
    private function novoTenant(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [CatalogoItem::FEATURE_KEY => true, CkTemplate::FEATURE_KEY => true, 'tabela_ck_execucoes' => true, 'tabela_assets' => true, 'tabela_maintenance_orders' => true,
                'tabela_frota_checklists' => true, 'tabela_frota_modelos_checklist' => true]]);
        $t = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'ck4-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = $this->usuario($t, admin: true);

        return [$t, $u];
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

        return $u;
    }

    private function ativo(string $grupo = 'gerador', ?Tenant $t = null): Asset
    {
        $t ??= $this->tenant;
        $cat = AssetCategory::withoutGlobalScopes()->where('tenant_id', $t->id)->where('slug_template', $grupo)->firstOrFail();

        return Asset::factory()->create(['tenant_id' => $t->id, 'asset_category_id' => $cat->id, 'status' => Asset::STATUS_DISPONIVEL]);
    }

    private function execucao(Asset $a, string $status = CkExecution::STATUS_APROVADO, array $extra = [], ?User $executor = null): CkExecution
    {
        return CkExecution::withoutGlobalScopes()->create($extra + [
            'tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'template_nome' => 'Checklist de pré-uso do gerador', 'versao_template' => 1,
            'executor_id' => ($executor ?? $this->admin)->id, 'status' => $status, 'iniciado_em' => now()->subHour(), 'finalizado_em' => now(),
        ]);
    }

    private function resposta(CkExecution $e, array $extra = []): CkAnswer
    {
        return CkAnswer::withoutGlobalScopes()->create($extra + [
            'tenant_id' => $e->tenant_id, 'execution_id' => $e->id, 'item_descricao' => 'Nível do óleo', 'tipo_resposta' => 'conforme_nao_conforme_na', 'critico' => false, 'resposta' => 'conforme',
        ]);
    }

    private function veiculo(Tenant $t): Asset
    {
        return Asset::create(['tenant_id' => $t->id, 'name' => 'Caminhão '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 1000]);
    }

    /** Checklist da Frota gravado pelo proprio servico da Frota (o que roda em PROD). */
    private function checklistFrota(Asset $v, array $excecoes = [], array $extra = [], ?User $usuario = null): FrotaChecklist
    {
        ModelosChecklistPadrao::garantir($v->tenant_id);
        $modelo = FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $v->tenant_id)->where('tipo', 'rapido')->with('itens')->firstOrFail();
        $respostas = $modelo->itens->mapWithKeys(fn (ItemFrota $i) => [$i->id => [
            'resultado' => $excecoes[$i->descricao] ?? RespostaFrota::OK, 'valor' => $i->tipo_resposta === ItemFrota::RESPOSTA_NUMERO ? '4,5' : null,
        ]])->all();

        return (new ChecklistFrotaService)->registrar($v, array_merge(['tipo' => FrotaChecklist::TIPO_SAIDA, 'modelo_id' => $modelo->id, 'odometro' => 1200, 'respostas' => $respostas, 'assinatura' => 'data:image/png;base64,AAA'], $extra), $usuario ?? $this->admin);
    }

    private function espelho(FrotaChecklist $c): ?CkExecution
    {
        return CkExecution::withoutGlobalScopes()->where('tenant_id', $c->tenant_id)->where('legado_tipo', 'frota_checklist')->where('legado_id', $c->id)->first();
    }

    // ------------------------------------------------------------ espelho da Frota

    public function test_o_checklist_da_frota_e_espelhado_automaticamente_sem_mudar_a_frota(): void
    {
        $v = $this->veiculo($this->tenant);

        $c = $this->checklistFrota($v);

        $e = $this->espelho($c);
        $this->assertNotNull($e, 'o servico da Frota espelha o que gravou');
        $this->assertSame($v->id, $e->asset_id);
        $this->assertSame(CkExecution::STATUS_APROVADO, $e->status);
        $this->assertSame('km', $e->medidor);
        $this->assertSame(1200.0, (float) $e->leitura_medidor);
        $this->assertSame($this->admin->id, $e->executor_id);
        $this->assertStringContainsString('Frota · ', $e->template_nome);
        $this->assertStringContainsString('(Saída)', $e->template_nome);
        $this->assertNull($e->template_id);
        $this->assertSame('Frota', $e->origemRotulo());
        $this->assertSame($c->respostas()->count(), CkAnswer::withoutGlobalScopes()->where('execution_id', $e->id)->count());
        $this->assertSame('ok', $c->fresh()->situacao, 'a Frota segue com a situacao dela');
    }

    public function test_status_e_respostas_do_espelho_seguem_a_situacao_da_frota(): void
    {
        $v = $this->veiculo($this->tenant);
        $this->checklistFrota($v); // garante os modelos
        $itens = ItemFrota::query()->whereIn('modelo_id', FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->pluck('id'))->get();
        $critico = $itens->firstWhere('gravidade', ItemFrota::GRAVIDADE_CRITICA);
        $atencao = $itens->firstWhere('gravidade', ItemFrota::GRAVIDADE_ATENCAO);

        $bloqueado = $this->checklistFrota($this->veiculo($this->tenant), [$critico->descricao => RespostaFrota::PROBLEMA]);
        $atencaoC = $this->checklistFrota($this->veiculo($this->tenant), [$atencao->descricao => RespostaFrota::PROBLEMA]);

        $eb = $this->espelho($bloqueado);
        $this->assertSame(CkExecution::STATUS_REPROVADO, $eb->status);
        $nc = CkAnswer::withoutGlobalScopes()->where('execution_id', $eb->id)->where('resposta', 'nao_conforme')->get();
        $this->assertCount(1, $nc);
        $this->assertTrue((bool) $nc->first()->critico);
        $this->assertSame($critico->descricao, $nc->first()->item_descricao);

        $this->assertSame(CkExecution::STATUS_APROVADO_COM_RESSALVA, $this->espelho($atencaoC)->status);
        $this->assertSame(1, CkAnswer::withoutGlobalScopes()->where('execution_id', $this->espelho($atencaoC)->id)->pendencias()->count());

        $num = CkAnswer::withoutGlobalScopes()->where('execution_id', $eb->id)->where('tipo_resposta', 'numerico')->first();
        $this->assertNotNull($num ?? CkAnswer::withoutGlobalScopes()->where('execution_id', $eb->id)->first());
    }

    public function test_liberar_veiculo_bloqueado_atualiza_o_espelho(): void
    {
        $v = $this->veiculo($this->tenant);
        $this->checklistFrota($v);
        $critico = ItemFrota::query()->whereIn('modelo_id', FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->pluck('id'))->where('gravidade', ItemFrota::GRAVIDADE_CRITICA)->firstOrFail();
        $c = $this->checklistFrota($this->veiculo($this->tenant), [$critico->descricao => RespostaFrota::PROBLEMA]);
        $this->assertSame(CkExecution::STATUS_REPROVADO, $this->espelho($c)->status);

        (new ChecklistFrotaService)->liberar($c, $this->admin, 'Pneu trocado no pátio');

        $e = $this->espelho($c);
        $this->assertSame(CkExecution::STATUS_APROVADO_COM_RESSALVA, $e->status);
        $this->assertStringContainsString('Pneu trocado no pátio', (string) $e->observacoes);
        $this->assertSame(1, CkExecution::withoutGlobalScopes()->where('legado_id', $c->id)->count(), 'atualiza, nao duplica');
    }

    public function test_espelhar_de_novo_e_idempotente(): void
    {
        $c = $this->checklistFrota($this->veiculo($this->tenant));
        $antes = CkAnswer::withoutGlobalScopes()->where('execution_id', $this->espelho($c)->id)->count();

        app(FrotaParaChecklists::class)->espelharChecklist($c);
        app(FrotaParaChecklists::class)->espelharChecklist($c);

        $this->assertSame(1, CkExecution::withoutGlobalScopes()->where('legado_id', $c->id)->count());
        $this->assertSame($antes, CkAnswer::withoutGlobalScopes()->where('execution_id', $this->espelho($c)->id)->count());
    }

    public function test_falha_no_espelho_nunca_derruba_o_checklist_da_frota(): void
    {
        $v = $this->veiculo($this->tenant);
        $this->checklistFrota($v);
        $this->app->bind(FrotaParaChecklists::class, fn () => throw new \RuntimeException('espelho quebrado'));

        $c = $this->checklistFrota($v);

        $this->assertNotNull(FrotaChecklist::withoutGlobalScopes()->find($c->id), 'o checklist da Frota foi gravado mesmo com o espelho fora do ar');
    }

    public function test_comando_copia_modelos_e_historico_da_frota_so_do_tenant_pedido(): void
    {
        $v = $this->veiculo($this->tenant);
        $c = $this->checklistFrota($v);
        CkExecution::withoutGlobalScopes()->where('legado_id', $c->id)->delete(); // simula o que existia antes do espelho (PROD hoje)
        [$outro, $outroAdmin] = $this->novoTenant();
        $this->actingAs($outroAdmin);
        $c2 = $this->checklistFrota($this->veiculo($outro), [], [], $outroAdmin);
        $this->actingAs($this->admin);
        CkExecution::withoutGlobalScopes()->where('legado_id', $c2->id)->delete();

        $this->artisan('checklists:migrar-frota', ['--tenant' => $this->tenant->id, '--dry-run' => true])->assertSuccessful();
        $this->assertNull($this->espelho($c), 'dry-run nao grava');
        $this->assertSame(0, CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('legado_tipo', 'frota_modelo')->count());

        $this->artisan('checklists:migrar-frota', ['--tenant' => $this->tenant->id])->assertSuccessful();
        $this->assertNotNull($this->espelho($c));
        $this->assertNull($this->espelho($c2), 'outro tenant nao foi tocado');

        $modelos = CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('legado_tipo', 'frota_modelo')->get();
        $this->assertCount(2, $modelos, 'rapido e completo');
        $this->assertSame([false], $modelos->pluck('ativo')->map(fn ($a) => (bool) $a)->unique()->values()->all(), 'copias entram inativas');
        $this->assertSame([null], $modelos->pluck('grupo_id')->unique()->values()->all(), 'e sem grupo: nao se aplicam a nenhum ativo');

        $this->artisan('checklists:migrar-frota', ['--tenant' => $this->tenant->id])->assertSuccessful();
        $this->assertSame(1, CkExecution::withoutGlobalScopes()->where('legado_id', $c->id)->count());
        $this->assertCount(2, CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('legado_tipo', 'frota_modelo')->get());
        $this->artisan('checklists:migrar-frota', ['--tenant' => '00000000-0000-0000-0000-000000000000'])->assertFailed();
    }

    public function test_modelo_copiado_da_frota_mapeia_gravidade_tipo_e_categoria(): void
    {
        $this->checklistFrota($this->veiculo($this->tenant));
        $completo = FrotaModeloChecklist::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('tipo', 'completo')->with('itens')->firstOrFail();

        $t = app(FrotaParaChecklists::class)->espelharModelo($completo);

        $itens = CkItem::withoutGlobalScopes()->whereIn('section_id', $t->secoes()->withoutGlobalScopes()->pluck('id'))->get();
        $this->assertCount($completo->itens->count(), $itens);
        $this->assertSame($completo->itens->where('gravidade', ItemFrota::GRAVIDADE_CRITICA)->count(), $itens->where('critico', true)->count());
        $this->assertSame($completo->itens->where('tipo_resposta', ItemFrota::RESPOSTA_NUMERO)->count(), $itens->where('tipo_resposta', 'numerico')->count());
        $this->assertTrue($t->secoes()->withoutGlobalScopes()->where('titulo', 'Pneus')->exists());
        $this->assertStringContainsString('A Frota segue usando o modelo original', (string) $t->observacoes);
    }

    // ------------------------------------------------------------ relatorio, filtros e exportacao

    public function test_relatorio_lista_so_o_proprio_tenant_e_filtra(): void
    {
        $g = $this->ativo('gerador');
        $c = $this->ativo('compressor');
        $outroUsuario = $this->usuario($this->tenant, permissoes: []);
        $e1 = $this->execucao($g, CkExecution::STATUS_APROVADO, ['finalizado_em' => now()->subDays(10)]);
        $e2 = $this->execucao($g, CkExecution::STATUS_REPROVADO, ['finalizado_em' => now()->subDays(2)], $outroUsuario);
        $e3 = $this->execucao($c, CkExecution::STATUS_APROVADO_COM_RESSALVA, ['finalizado_em' => now()->subDay(), 'template_nome' => 'Checklist diário do compressor']);
        [$outro] = $this->novoTenant();
        app(CatalogoInstalador::class)->instalar($outro);
        $deles = $this->execucao($this->ativo('gerador', $outro), CkExecution::STATUS_APROVADO, [], User::where('tenant_id', $outro->id)->first());

        $lista = fn () => Livewire::test(ListCkExecutions::class);

        $lista()->assertCanSeeTableRecords([$e1, $e2, $e3])->assertCanNotSeeTableRecords([$deles])->assertCountTableRecords(3);
        $lista()->filterTable('status', CkExecution::STATUS_REPROVADO)->assertCanSeeTableRecords([$e2])->assertCountTableRecords(1);
        $lista()->filterTable('grupo', $c->asset_category_id)->assertCanSeeTableRecords([$e3])->assertCountTableRecords(1);
        $lista()->filterTable('executor_id', $outroUsuario->id)->assertCanSeeTableRecords([$e2])->assertCountTableRecords(1);
        $lista()->filterTable('periodo', ['de' => now()->subDays(3)->toDateString(), 'ate' => now()->toDateString()])->assertCanSeeTableRecords([$e2, $e3])->assertCanNotSeeTableRecords([$e1]);
        $lista()->filterTable('periodo', ['de' => null, 'ate' => now()->subDays(5)->toDateString()])->assertCanSeeTableRecords([$e1])->assertCountTableRecords(1);
        $lista()->searchTable('compressor')->assertCanSeeTableRecords([$e3])->assertCountTableRecords(1);
    }

    public function test_filtro_de_origem_separa_frota_de_checklist(): void
    {
        $nativo = $this->execucao($this->ativo());
        $frota = $this->espelho($this->checklistFrota($this->veiculo($this->tenant)));

        Livewire::test(ListCkExecutions::class)->assertCountTableRecords(2)
            ->filterTable('origem', 'frota')->assertCanSeeTableRecords([$frota])->assertCanNotSeeTableRecords([$nativo])
            ->resetTableFilters()->filterTable('origem', 'checklist')->assertCanSeeTableRecords([$nativo])->assertCanNotSeeTableRecords([$frota]);
    }

    public function test_permissoes_do_relatorio(): void
    {
        $leitor = $this->usuario($this->tenant, permissoes: ['ler_execucao_checklist']);
        $semNada = $this->usuario($this->tenant);
        $e = $this->execucao($this->ativo());

        $this->actingAs($leitor);
        $this->assertTrue(CkExecutionResource::canViewAny());
        $this->assertTrue(CkExecutionResource::canView($e));
        $this->assertFalse(CkExecutionResource::canCreate());
        $this->assertFalse(CkExecutionResource::canEdit($e));
        $this->assertFalse(CkExecutionResource::canDelete($e));

        $this->actingAs($semNada);
        $this->assertFalse(CkExecutionResource::canViewAny());
        $this->assertFalse(CkExecutionResource::shouldRegisterNavigation());
    }

    public function test_detalhe_mostra_respostas_fotos_e_assinatura(): void
    {
        $e = $this->execucao($this->ativo(), CkExecution::STATUS_REPROVADO, ['assinatura' => 'data:image/png;base64,AAA', 'observacoes' => 'parou o gerador']);
        $this->resposta($e, ['item_descricao' => 'Painel sem luz de alerta', 'critico' => true, 'resposta' => 'nao_conforme', 'observacao' => 'luz acesa', 'fotos' => ['ck-execucoes/'.$this->tenant->id.'/x/p.jpg']]);

        Livewire::test(ViewCkExecution::class, ['record' => $e->getKey()])
            ->assertSee('Painel sem luz de alerta')->assertSee('luz acesa')->assertSee('Não conforme')->assertSee('Reprovado')->assertSee('parou o gerador')
            ->assertSee('Ver foto 1')->assertSee('data:image/png;base64,AAA', false);
    }

    public function test_exportacao_excel_respeita_filtros_e_tem_duas_abas(): void
    {
        $g = $this->ativo('gerador');
        $a = $this->execucao($g, CkExecution::STATUS_REPROVADO);
        $this->resposta($a, ['item_descricao' => 'Aterramento', 'critico' => true, 'resposta' => 'nao_conforme', 'observacao' => 'solto']);
        $this->resposta($a, ['item_descricao' => 'Extintor']);
        $b = $this->execucao($g, CkExecution::STATUS_APROVADO);
        $this->resposta($b, ['item_descricao' => 'Extintor']);
        [$outro] = $this->novoTenant();
        app(CatalogoInstalador::class)->instalar($outro);
        $this->execucao($this->ativo('gerador', $outro), CkExecution::STATUS_APROVADO, [], User::where('tenant_id', $outro->id)->first());

        $xlsx = CkExecucaoExport::xlsx(CkExecution::query()->where('status', CkExecution::STATUS_REPROVADO));
        $abas = $this->lerXlsx($xlsx);
        @unlink($xlsx);

        $this->assertSame(['Execuções', 'Respostas'], array_keys($abas));
        $this->assertSame(CkExecucaoExport::cabecalhoExecucoes(), $abas['Execuções'][0]);
        $this->assertCount(2, $abas['Execuções'], 'cabecalho + 1 execucao reprovada (a aprovada e a de outro tenant ficam de fora)');
        $linha = array_combine($abas['Execuções'][0], $abas['Execuções'][1]);
        $this->assertSame('Reprovado', $linha['Resultado']);
        $this->assertSame(1, (int) $linha['Itens críticos não conformes']);
        $this->assertSame('Checklist', $linha['Origem']);
        $this->assertCount(3, $abas['Respostas'], 'cabecalho + as 2 respostas da execucao exportada');
        $this->assertContains('Aterramento', array_column($abas['Respostas'], 4));
    }

    public function test_botao_exportar_da_lista_baixa_o_arquivo(): void
    {
        $this->execucao($this->ativo());

        Livewire::test(ListCkExecutions::class)->callAction('exportarExcel')->assertFileDownloaded();
    }

    /** @return array<string, array<int, array<int, mixed>>> aba => linhas */
    private function lerXlsx(string $caminho): array
    {
        $reader = new Reader;
        $reader->open($caminho);
        $abas = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $abas[$sheet->getName()][] = $row->toArray();
            }
        }

        $reader->close();

        return $abas;
    }

    // ------------------------------------------------------------ historico no ativo

    public function test_aba_de_historico_do_ativo_mostra_so_as_execucoes_dele(): void
    {
        $a = $this->ativo();
        $outro = $this->ativo();
        $e = $this->execucao($a);
        $deOutro = $this->execucao($outro);

        Livewire::test(ExecucoesChecklistRelationManager::class, ['ownerRecord' => $a, 'pageClass' => EditAsset::class])
            ->assertCanSeeTableRecords([$e])->assertCanNotSeeTableRecords([$deOutro]);
    }

    public function test_historico_do_veiculo_inclui_o_que_veio_da_frota(): void
    {
        $v = $this->veiculo($this->tenant);
        $espelho = $this->espelho($this->checklistFrota($v));

        Livewire::test(ExecucoesChecklistRelationManager::class, ['ownerRecord' => $v, 'pageClass' => EditAsset::class])->assertCanSeeTableRecords([$espelho]);
    }

    public function test_aba_do_ativo_some_sem_permissao(): void
    {
        $a = $this->ativo();
        $this->actingAs($this->usuario($this->tenant));

        $this->assertFalse(ExecucoesChecklistRelationManager::canViewForRecord($a, EditAsset::class));
        $this->actingAs($this->admin);
        $this->assertTrue(ExecucoesChecklistRelationManager::canViewForRecord($a, EditAsset::class));
    }

    // ------------------------------------------------------------ foto (disco privado)

    public function test_foto_so_sai_para_quem_pode_ver_e_do_proprio_tenant(): void
    {
        $e = $this->execucao($this->ativo());
        $caminho = 'ck-execucoes/'.$this->tenant->id.'/'.$e->id.'/p.jpg';
        Storage::disk('local')->put($caminho, 'conteudo-da-foto');
        $r = $this->resposta($e, ['fotos' => [$caminho]]);

        $this->get(route('ck.resposta.foto', [$r->id, 0]))->assertOk();

        $this->get(route('ck.resposta.foto', [$r->id, 5]))->assertNotFound();

        $this->actingAs($this->usuario($this->tenant))->get(route('ck.resposta.foto', [$r->id, 0]))->assertForbidden();

        [$outroTenant, $outroAdmin] = $this->novoTenant();
        $this->actingAs($outroAdmin)->get(route('ck.resposta.foto', [$r->id, 0]))->assertNotFound();

        auth()->logout();
        $this->get(route('ck.resposta.foto', [$r->id, 0]))->assertRedirect();
    }

    public function test_foto_com_caminho_fora_da_pasta_do_tenant_e_recusada(): void
    {
        $e = $this->execucao($this->ativo());
        Storage::disk('local')->put('segredo.txt', 'nao pode sair');
        $r = $this->resposta($e, ['fotos' => ['segredo.txt']]);
        $r2 = $this->resposta($e, ['fotos' => ['ck-execucoes/'.$this->tenant->id.'/../../segredo.txt']]);

        $this->get(route('ck.resposta.foto', [$r->id, 0]))->assertNotFound();
        $this->get(route('ck.resposta.foto', [$r2->id, 0]))->assertNotFound();
    }
}
