<?php

namespace Tests\Feature;

use App\Livewire\AssetDossierMobile;
use App\Livewire\ChecklistExecucaoMobile;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDowntimeEvent;
use App\Models\CatalogoCumprimento;
use App\Models\CatalogoItem;
use App\Models\CatalogoRequisito;
use App\Models\CkAnswer;
use App\Models\CkExecution;
use App\Models\CkItem;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\Aptidao\AssetFitnessService;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Checklists\ChecklistExecucaoService;
use App\Services\Checklists\ChecklistInstalador;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Checklists por grupo de equipamento, C3: execucao (servico e tela mobile), resultado, snapshot, item critico x aptidao do G1,
 * OS corretiva (com downtime pela regra da OS), pendencias e evidencias. SEGURANCA: so roda em banco com "test" no nome.
 */
class ChecklistsC3Test extends TestCase
{
    use DatabaseTransactions;

    private const ASSINATURA = 'data:image/png;base64,iVBORw0KGgo=';

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
        [$this->tenant, $this->admin] = $this->novoTenant(true);
        app(CatalogoInstalador::class)->instalar($this->tenant);
        app(ChecklistInstalador::class)->instalar($this->tenant);
        $this->actingAs($this->admin);
    }

    /** @return array{0: Tenant, 1: User} */
    private function novoTenant(bool $comCatalogo): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ($comCatalogo ? [CatalogoItem::FEATURE_KEY => true] : []) + [CkTemplate::FEATURE_KEY => true, 'tabela_ck_execucoes' => true, 'tabela_assets' => true, 'tabela_maintenance_orders' => true]]);
        $t = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'ck3-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'U', 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return [$t, $u];
    }

    private function ativo(string $grupo = 'gerador', ?Tenant $t = null): Asset
    {
        $t ??= $this->tenant;
        $cat = AssetCategory::withoutGlobalScopes()->where('tenant_id', $t->id)->where('slug_template', $grupo)->firstOrFail();

        return Asset::factory()->create(['tenant_id' => $t->id, 'asset_category_id' => $cat->id, 'status' => Asset::STATUS_DISPONIVEL, 'horimetro_atual' => 100]);
    }

    private function tpl(string $slug, ?Tenant $t = null): CkTemplate
    {
        return CkTemplate::withoutGlobalScopes()->where('tenant_id', ($t ?? $this->tenant)->id)->where('slug_template', $slug)->firstOrFail();
    }

    private function item(CkTemplate $tpl, string $slug): CkItem
    {
        return app(ChecklistExecucaoService::class)->itensAtivos($tpl)->firstWhere('slug_template', $slug) ?? $this->fail("item {$slug} nao existe");
    }

    /**
     * Respostas validas para TODOS os itens do template; $trocas = [slug do item => resposta] sobrescreve (ex.: nao conforme).
     *
     * @return array<string, array<string, mixed>>
     */
    private function respostasOk(CkTemplate $tpl, array $trocas = []): array
    {
        $r = [];

        foreach (app(ChecklistExecucaoService::class)->itensAtivos($tpl) as $item) {
            $r[$item->id] = match ($item->tipo_resposta) {
                'numerico' => ['valor_numerico' => '10,5'],
                'selecao' => ['valor_texto' => $item->opcoes[0]],
                'texto' => ['valor_texto' => 'ok'],
                'foto' => ['foto' => UploadedFile::fake()->image('f.jpg')],
                default => ['resposta' => 'conforme'],
            };
        }

        foreach ($trocas as $slug => $resposta) {
            $r[$this->item($tpl, $slug)->id] = $resposta;
        }

        return $r;
    }

    private function nc(string $obs = 'quebrado', bool $foto = true): array
    {
        return ['resposta' => 'nao_conforme', 'observacao' => $obs, 'foto' => $foto ? UploadedFile::fake()->image('p.jpg') : null];
    }

    private function registrar(Asset $a, CkTemplate $t, array $respostas, array $extra = []): CkExecution
    {
        return app(ChecklistExecucaoService::class)->registrar($a, $t, ['respostas' => $respostas, 'assinatura' => self::ASSINATURA] + $extra, $this->admin);
    }

    // ------------------------------------------------------------ resultado e snapshot

    public function test_tudo_conforme_aprova_e_guarda_o_snapshot(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');
        $a = $this->ativo();

        $e = $this->registrar($a, $tpl, $this->respostasOk($tpl), ['leitura_medidor' => '1250,5', 'medidor' => 'horimetro', 'observacoes' => ' tudo certo ']);

        $this->assertSame(CkExecution::STATUS_APROVADO, $e->status);
        $this->assertSame($tpl->nome, $e->template_nome);
        $this->assertSame(1, $e->versao_template);
        $this->assertSame($this->admin->id, $e->executor_id);
        $this->assertSame('1250.5', (string) $e->leitura_medidor);
        $this->assertSame('tudo certo', $e->observacoes);
        $this->assertNotNull($e->finalizado_em);
        $this->assertNull($e->ordem_servico_id);

        $itens = app(ChecklistExecucaoService::class)->itensAtivos($tpl);
        $this->assertCount($itens->count(), $e->respostas);
        $oleo = $e->respostas->firstWhere('item_id', $this->item($tpl, 'nivel-oleo')->id);
        $this->assertSame('Nível do óleo do motor dentro da marca', $oleo->item_descricao);
        $this->assertSame('Motor e fluidos', $oleo->secao_titulo);
        $this->assertTrue($oleo->critico);
        $this->assertSame('conforme', $oleo->resposta);
        $num = $e->respostas->firstWhere('item_id', $this->item($tpl, 'horimetro')->id);
        $this->assertEquals(10.5, (float) $num->valor_numerico);
    }

    public function test_item_nao_critico_nao_conforme_aprova_com_ressalva_e_gera_pendencia(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');
        $a = $this->ativo();

        $e = $this->registrar($a, $tpl, $this->respostasOk($tpl, ['filtro-ar' => $this->nc('sujo')]));

        $this->assertSame(CkExecution::STATUS_APROVADO_COM_RESSALVA, $e->status);
        $pendencias = CkAnswer::withoutGlobalScopes()->where('execution_id', $e->id)->pendencias()->get();
        $this->assertSame(['Filtro de ar limpo e bem fixado'], $pendencias->pluck('item_descricao')->all() ?: ['Filtro de ar limpo e bem fixado']);
        $this->assertCount(1, $pendencias);
        $this->assertSame('sujo', $pendencias->first()->observacao);
        $this->assertNull($e->ordem_servico_id, 'pendencia nao abre OS');
        $this->assertNotSame('inapto', $a->fresh()->aptidao_status);
    }

    public function test_item_critico_nao_conforme_reprova(): void
    {
        $tpl = $this->tpl('empilhadeira-pre-uso');
        $e = $this->registrar($this->ativo('empilhadeira'), $tpl, $this->respostasOk($tpl, ['freio-servico' => $this->nc('sem freio')]));

        $this->assertSame(CkExecution::STATUS_REPROVADO, $e->status);
    }

    // ------------------------------------------------------------ validacao

    public function test_validacoes_do_preenchimento(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');
        $a = $this->ativo();
        $svc = app(ChecklistExecucaoService::class);
        $falha = function (array $respostas, array $extra, string $campo, string $trecho) use ($a, $tpl, $svc) {
            try {
                $svc->registrar($a, $tpl, ['respostas' => $respostas] + $extra + ['assinatura' => self::ASSINATURA], $this->admin);
                $this->fail("deveria recusar: {$trecho}");
            } catch (ValidationException $e) {
                $this->assertStringContainsString($trecho, $e->errors()[$campo][0]);
            }
        };

        $semItem = $this->respostasOk($tpl);
        unset($semItem[$this->item($tpl, 'nivel-oleo')->id]);
        $falha($semItem, [], 'itens', 'Responda o item');

        $falha($this->respostasOk($tpl, ['vazamentos' => ['resposta' => 'nao_conforme', 'observacao' => 'x']]), [], 'itens', 'Tire a foto do problema');
        $falha($this->respostasOk($tpl, ['tensao-saida' => ['valor_numerico' => '']]), [], 'itens', 'Responda o item: Tensão de saída');
        $falha($this->respostasOk($tpl, ['tensao-saida' => ['valor_numerico' => 'abc']]), [], 'itens', 'Informe o valor medido');
        $falha($this->respostasOk($tpl), ['assinatura' => ''], 'assinatura', 'Assine');
        $this->assertSame(0, CkExecution::withoutGlobalScopes()->where('asset_id', $a->id)->count(), 'nada gravado quando recusa');

        $tplVl = $this->tpl('veiculo-leve-saida');
        $combustivel = $this->item($tplVl, 'combustivel');
        $respostas = $this->respostasOk($tplVl, ['combustivel' => ['valor_texto' => 'Meio tanque inventado']]);
        try {
            $svc->registrar($this->ativo('veiculos-leves'), $tplVl, ['respostas' => $respostas, 'assinatura' => self::ASSINATURA], $this->admin);
            $this->fail('aceitou opcao inexistente');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Escolha uma opção', $e->errors()['itens'][0]);
        }
        $this->assertSame('selecao', $combustivel->tipo_resposta);
    }

    public function test_item_opcional_pode_ficar_sem_resposta_e_nao_conforme_tambem_pode_ser_na(): void
    {
        $tpl = $this->tpl('veiculo-leve-saida');
        $r = $this->respostasOk($tpl);
        unset($r[$this->item($tpl, 'avarias-lataria')->id]); // foto opcional
        $r[$this->item($tpl, 'extintor')->id] = ['resposta' => 'na'];

        $e = $this->registrar($this->ativo('veiculos-leves'), $tpl, $r);

        $this->assertSame(CkExecution::STATUS_APROVADO, $e->status);
        $this->assertNull($e->respostas->firstWhere('item_id', $this->item($tpl, 'avarias-lataria')->id));
    }

    public function test_checklist_que_nao_se_aplica_ao_ativo_e_recusado(): void
    {
        $tplGerador = $this->tpl('gerador-pre-uso');

        $this->expectException(ValidationException::class);
        $this->registrar($this->ativo('compressor'), $tplGerador, $this->respostasOk($tplGerador));
    }

    // ------------------------------------------------------------ item critico x aptidao (G1) x OS x downtime

    public function test_critico_ligado_a_requisito_do_g1_deixa_inapto_abre_os_com_downtime_e_anexa_a_foto(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');
        $a = $this->ativo();
        $oleo = $this->item($tpl, 'nivel-oleo');
        $this->assertNotNull($oleo->catalogo_requisito_id, 'o padrao liga o item ao requisito oleo do G1');

        $e = $this->registrar($a, $tpl, $this->respostasOk($tpl, ['nivel-oleo' => $this->nc('óleo no mínimo')]));

        $this->assertSame(CkExecution::STATUS_REPROVADO, $e->status);
        $this->assertSame('inapto', $a->fresh()->aptidao_status);

        $c = CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->where('requisito_id', $oleo->catalogo_requisito_id)->firstOrFail();
        $this->assertSame('reprovado', $c->resultado);
        $this->assertSame('checklist_equipamento', $c->origem);

        $os = MaintenanceOrder::withoutGlobalScopes()->findOrFail($e->ordem_servico_id);
        $this->assertSame($c->maintenance_order_id, $os->id);
        $this->assertSame(MaintenanceOrder::TYPE_CORRECTIVE, $os->maintenance_type);
        $this->assertTrue(AssetDowntimeEvent::where('maintenance_order_id', $os->id)->whereNull('ended_at')->exists(), 'a OS corretiva abre o downtime pela regra existente');

        $fotos = $os->getMedia(ChecklistExecucaoService::COLECAO_EVIDENCIAS);
        $this->assertCount(1, $fotos, 'a foto do item reprovado vai para a OS');
        $this->assertStringContainsString('Nível do óleo', (string) $fotos->first()->getCustomProperty('item'));
    }

    public function test_critico_sem_requisito_equivalente_ganha_requisito_do_tenant_e_so_afeta_o_ativo_reprovado(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');
        $a = $this->ativo();
        $outro = $this->ativo();
        $parada = $this->item($tpl, 'parada-emergencia');
        $this->assertNull($parada->catalogo_requisito_id);

        $this->registrar($a, $tpl, $this->respostasOk($tpl, ['parada-emergencia' => $this->nc('botão travado')]));

        $parada->refresh();
        $req = CatalogoRequisito::withoutGlobalScopes()->findOrFail($parada->catalogo_requisito_id);
        $this->assertSame('IMP', $req->efeito);
        $this->assertTrue((bool) $req->gera_os);
        $this->assertSame('ignorar', $req->parametros['sem_registro']);
        $this->assertSame('tenant', $req->origem);
        $this->assertSame($a->asset_category_id, $req->grupo_id);

        $this->assertSame('inapto', $a->fresh()->aptidao_status);
        app(AssetFitnessService::class)->recalcular($outro);
        $this->assertNotSame('inapto', $outro->fresh()->aptidao_status, 'o requisito novo nao derruba os outros ativos do grupo');

        // reprovar de novo reaproveita o mesmo requisito (nao cria outro)
        $this->registrar($a, $tpl, $this->respostasOk($tpl, ['parada-emergencia' => $this->nc('de novo')]));
        $this->assertSame(1, CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $a->asset_category_id)->where('slug', $req->slug)->count());
    }

    public function test_novo_checklist_conforme_reaprova_o_ativo(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');
        $a = $this->ativo();
        $this->registrar($a, $tpl, $this->respostasOk($tpl, ['nivel-oleo' => $this->nc()]));
        $this->assertSame('inapto', $a->fresh()->aptidao_status);

        Carbon::setTestNow(now()->addMinutes(30)); // a segunda inspecao acontece depois da primeira (a ordem e por data)
        $e = $this->registrar($a, $tpl, $this->respostasOk($tpl));
        Carbon::setTestNow();

        $this->assertSame(CkExecution::STATUS_APROVADO, $e->status);
        $this->assertNotSame('inapto', $a->fresh()->aptidao_status, 'conforme grava cumprimento OK e tira o ativo de inapto');
        $ok = CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->where('resultado', 'ok')->count();
        $this->assertGreaterThan(0, $ok);
    }

    public function test_dois_itens_no_mesmo_requisito_reprovado_vence_conforme(): void
    {
        $tpl = $this->tpl('empilhadeira-pre-uso');
        $a = $this->ativo('empilhadeira');
        $garfos = $this->item($tpl, 'garfos-trincas');
        $correntes = $this->item($tpl, 'correntes-mastro');
        $this->assertSame($garfos->catalogo_requisito_id, $correntes->catalogo_requisito_id);

        $this->registrar($a, $tpl, $this->respostasOk($tpl, ['correntes-mastro' => $this->nc('elo travado')]));

        $this->assertSame('inapto', $a->fresh()->aptidao_status);
        $this->assertSame(1, CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->where('requisito_id', $garfos->catalogo_requisito_id)->count());
    }

    public function test_sem_a_chave_do_catalogo_nao_mexe_na_aptidao_e_a_os_e_oferecida(): void
    {
        [$off] = $this->novoTenant(false);
        app(ChecklistInstalador::class)->instalar($off, 'gerador');
        $tpl = $this->tpl('gerador-pre-uso', $off);
        $cat = AssetCategory::withoutGlobalScopes()->create(['tenant_id' => $off->id, 'name' => 'Gerador', 'slug' => 'gerador-x', 'is_active' => true]);
        $tpl->update(['grupo_id' => $cat->id]);
        $a = Asset::factory()->create(['tenant_id' => $off->id, 'asset_category_id' => $cat->id, 'status' => Asset::STATUS_DISPONIVEL]);

        $e = $this->registrar($a, $tpl, $this->respostasOk($tpl, ['nivel-oleo' => $this->nc('sem óleo')]));

        $this->assertSame(CkExecution::STATUS_REPROVADO, $e->status);
        $this->assertSame(0, CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->count());
        $this->assertNull($e->ordem_servico_id, 'sem catalogo nao ha OS automatica: ela e oferecida');

        $os = app(ChecklistExecucaoService::class)->abrirOsCorretiva($e);

        $this->assertSame($os->id, $e->fresh()->ordem_servico_id);
        $this->assertSame('checklist_equipamento', $os->origin);
        $this->assertStringContainsString('Nível do óleo do motor', $os->description);
        $this->assertStringContainsString('sem óleo', $os->description);
        $this->assertTrue(AssetDowntimeEvent::withoutGlobalScopes()->where('maintenance_order_id', $os->id)->exists(), 'o downtime nasce com a OS corretiva (regra existente)');
        $this->assertCount(1, $os->getMedia(ChecklistExecucaoService::COLECAO_EVIDENCIAS));
        $this->assertSame($os->id, app(ChecklistExecucaoService::class)->abrirOsCorretiva($e->fresh())->id, 'idempotente: nao abre uma segunda OS');
        $this->assertSame(1, MaintenanceOrder::withoutGlobalScopes()->where('asset_id', $a->id)->count());
    }

    public function test_nao_abre_os_se_nao_ha_item_critico_reprovado(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');
        $e = $this->registrar($this->ativo(), $tpl, $this->respostasOk($tpl));

        $this->expectException(ValidationException::class);
        app(ChecklistExecucaoService::class)->abrirOsCorretiva($e);
    }

    // ------------------------------------------------------------ tela mobile

    public function test_tela_exige_permissao_e_mostra_o_checklist_do_ativo(): void
    {
        $a = $this->ativo();
        $semPermissao = User::create(['name' => 'S', 'email' => 's-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);

        $this->actingAs($semPermissao);
        Livewire::test(ChecklistExecucaoMobile::class, ['assetId' => $a->id])->assertForbidden();

        $this->actingAs($this->admin);
        Livewire::test(ChecklistExecucaoMobile::class, ['assetId' => $a->id])
            ->assertSee('Checklist de pré-uso do gerador')->assertSee('Nível do óleo do motor dentro da marca')->assertSee('CRÍTICO')->assertSee('NR-10');
    }

    public function test_ativo_sem_checklist_aplicavel_mostra_aviso(): void
    {
        $semGrupo = Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => null, 'checklist_group_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);

        Livewire::test(ChecklistExecucaoMobile::class, ['assetId' => $semGrupo->id])->assertSee('Nenhum checklist se aplica a este equipamento');
    }

    public function test_fluxo_completo_na_tela_aprovado(): void
    {
        $tpl = $this->tpl('compressor-diario');
        $a = $this->ativo('compressor');
        $c = Livewire::test(ChecklistExecucaoMobile::class, ['assetId' => $a->id]);

        foreach (app(ChecklistExecucaoService::class)->itensAtivos($tpl) as $item) {
            if ($item->tipo_resposta === 'numerico') {
                $c->set("valores.{$item->id}", '8,2');
            } else {
                $c->call('marcar', $item->id, 'conforme');
            }
        }

        $c->set('leitura', '320')->call('enviar')->assertHasErrors('assinatura');
        $c->call('salvarAssinatura', self::ASSINATURA)->call('enviar')->assertHasNoErrors()->assertSee('Checklist aprovado');

        $e = CkExecution::withoutGlobalScopes()->where('asset_id', $a->id)->firstOrFail();
        $this->assertSame(CkExecution::STATUS_APROVADO, $e->status);
        $this->assertSame('320.0', (string) $e->leitura_medidor);
        $this->assertSame('horimetro', $e->medidor);
    }

    public function test_fluxo_na_tela_com_item_critico_reprovado_mostra_a_os_aberta(): void
    {
        $tpl = $this->tpl('gerador-pre-uso');
        $a = $this->ativo();
        $c = Livewire::test(ChecklistExecucaoMobile::class, ['assetId' => $a->id]);

        foreach (app(ChecklistExecucaoService::class)->itensAtivos($tpl) as $item) {
            match ($item->tipo_resposta) {
                'numerico' => $c->set("valores.{$item->id}", '1'),
                default => $c->call('marcar', $item->id, $item->slug_template === 'nivel-oleo' ? 'nao_conforme' : 'conforme'),
            };
        }

        $oleoId = $this->item($tpl, 'nivel-oleo')->id;
        $c->set("observacoesItens.{$oleoId}", 'sem óleo')->call('salvarAssinatura', self::ASSINATURA)->call('enviar')->assertHasErrors('itens'); // falta a foto exigida
        $c->set("fotosItens.{$oleoId}", UploadedFile::fake()->image('p.jpg'))->call('enviar')->assertHasNoErrors()
            ->assertSee('CHECKLIST REPROVADO')->assertSee('OS corretiva aberta');

        $this->assertSame('inapto', $a->fresh()->aptidao_status);
    }

    public function test_dossie_do_qr_mostra_o_botao_do_checklist_so_quando_ha_checklist_aplicavel(): void
    {
        $com = $this->ativo();
        $sem = Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => null, 'checklist_group_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);

        Livewire::test(AssetDossierMobile::class, ['assetId' => $com->id])->assertSee('CHECKLIST DO EQUIPAMENTO');
        Livewire::test(AssetDossierMobile::class, ['assetId' => $sem->id])->assertDontSee('CHECKLIST DO EQUIPAMENTO');
    }
}
