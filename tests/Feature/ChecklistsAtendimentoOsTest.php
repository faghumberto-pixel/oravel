<?php

namespace Tests\Feature;

use App\Livewire\ChecklistExecucaoDesktop;
use App\Livewire\ChecklistExecucaoMobile;
use App\Livewire\MaintenanceOrderFieldWizard;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
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
use App\Services\Checklists\ChecklistExecucaoService;
use App\Services\Checklists\ChecklistInstalador;
use App\Services\Checklists\ChecklistsDaOs;
use App\Support\CkExecucaoExport;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * O checklist faz parte do ATENDIMENTO da OS: tela desktop alem da de celular, folha impressa com o checklist, execucao amarrada a OS
 * (registrada contra ela) e, no Modo Campo, OS so conclui com o checklist feito. SEGURANCA: so roda em banco com "test" no nome.
 */
class ChecklistsAtendimentoOsTest extends TestCase
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
        $t = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'atos-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'U '.uniqid(), 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return [$t, $u->refresh()];
    }

    private function ativo(string $grupo = 'gerador', ?Tenant $t = null): Asset
    {
        $t ??= $this->tenant;
        $cat = AssetCategory::withoutGlobalScopes()->where('tenant_id', $t->id)->where('slug_template', $grupo)->firstOrFail();

        return Asset::factory()->create(['tenant_id' => $t->id, 'asset_category_id' => $cat->id, 'status' => Asset::STATUS_DISPONIVEL]);
    }

    private function os(Asset $a, array $extra = []): MaintenanceOrder
    {
        return MaintenanceOrder::create($extra + ['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'description' => 'x', 'maintenance_type' => 'Preventiva', 'status' => 'Aberto', 'priority' => 'media']);
    }

    private function tpl(string $slug): CkTemplate
    {
        return CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', $slug)->firstOrFail();
    }

    /** Respostas validas para todos os itens (tudo conforme / numero / opcao / texto). */
    private function respostasOk(CkTemplate $tpl): array
    {
        $r = [];

        foreach (app(ChecklistExecucaoService::class)->itensAtivos($tpl) as $item) {
            $r[$item->id] = match ($item->tipo_resposta) {
                'numerico' => ['valor_numerico' => '10'], 'selecao' => ['valor_texto' => $item->opcoes[0]], 'texto' => ['valor_texto' => 'ok'],
                'foto' => ['foto' => UploadedFile::fake()->image('f.jpg')], default => ['resposta' => 'conforme'],
            };
        }

        return $r;
    }

    private function fazerChecklist(Asset $a, CkTemplate $tpl, ?MaintenanceOrder $os = null): CkExecution
    {
        return app(ChecklistExecucaoService::class)->registrar($a, $tpl, ['respostas' => $this->respostasOk($tpl), 'assinatura' => self::ASSINATURA, 'os_id' => $os?->id], $this->admin);
    }

    // ------------------------------------------------------------ tela desktop

    public function test_a_tela_desktop_abre_larga_e_sem_a_moldura_de_celular(): void
    {
        $a = $this->ativo('gerador');
        $tpl = $this->tpl('gerador-pre-uso');

        $r = $this->get(route('ck.execucao.desktop', ['assetId' => $a->id, 'checklist' => $tpl->id]))->assertOk();

        $r->assertSee('id="ckd"', false)->assertSee('Checklist de pré-uso do gerador')->assertSee('Enviar checklist')->assertSee('Nível do óleo do motor dentro da marca')
            ->assertSee('Voltar ao equipamento')->assertSee('Confirmar assinatura');
        $r->assertDontSee('md:w-[390px]', false);   // a moldura de celular e so da tela mobile

        $celular = $this->get(route('ck.execucao.mobile', ['assetId' => $a->id]))->assertOk();
        $celular->assertSee('md:w-[390px]', false);
    }

    public function test_a_tela_desktop_registra_o_checklist_na_os_com_a_mesma_logica_do_celular(): void
    {
        $a = $this->ativo('gerador');
        $tpl = $this->tpl('gerador-pre-uso');
        $os = $this->os($a);
        $c = Livewire::withQueryParams(['checklist' => $tpl->id, 'os' => $os->id])->test(ChecklistExecucaoDesktop::class, ['assetId' => $a->id]);

        $this->assertSame($os->id, $c->get('osId'));
        $this->assertSame($tpl->id, $c->get('templateId'));

        foreach (app(ChecklistExecucaoService::class)->itensAtivos($tpl) as $item) {
            $item->tipo_resposta === 'numerico' ? $c->set("valores.{$item->id}", '5') : $c->call('marcar', $item->id, 'conforme');
        }

        $c->call('enviar')->assertHasErrors('assinatura');
        $c->call('salvarAssinatura', self::ASSINATURA)->call('enviar')->assertHasNoErrors()->assertSee('Checklist aprovado')->assertSee('Voltar à OS');

        $e = CkExecution::withoutGlobalScopes()->where('asset_id', $a->id)->firstOrFail();
        $this->assertSame($os->id, $e->os_id, 'o checklist ficou registrado contra a OS');
    }

    public function test_a_tela_desktop_exige_permissao_e_o_link_de_volta_vai_para_a_os(): void
    {
        $a = $this->ativo('gerador');
        $sem = User::create(['name' => 'S', 'email' => 's-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);

        $this->actingAs($sem);
        Livewire::test(ChecklistExecucaoDesktop::class, ['assetId' => $a->id])->assertForbidden();

        $this->actingAs($this->admin);
        $os = $this->os($a);
        $mobile = Livewire::withQueryParams(['os' => $os->id])->test(ChecklistExecucaoMobile::class, ['assetId' => $a->id])->instance();
        $this->assertStringContainsString('/maintenance-orders/'.$os->id.'/campo', $mobile->urlDeVolta(), 'no celular volta para a etapa do checklist do Modo Campo');
        $this->assertStringContainsString('step=2', $mobile->urlDeVolta());
        $desktop = Livewire::withQueryParams(['os' => $os->id])->test(ChecklistExecucaoDesktop::class, ['assetId' => $a->id])->instance();
        $this->assertStringContainsString('/maintenance-orders/', $desktop->urlDeVolta(true));
    }

    // ------------------------------------------------------------ registro contra a OS

    public function test_so_amarra_a_os_do_mesmo_equipamento_e_do_mesmo_tenant(): void
    {
        $a = $this->ativo('gerador');
        $outroAtivo = $this->ativo('gerador');
        $tpl = $this->tpl('gerador-pre-uso');
        [$outroTenant] = $this->novoTenant(true);
        app(CatalogoInstalador::class)->instalar($outroTenant);
        $osDeles = $this->os($this->ativo('gerador', $outroTenant));

        $os1 = $this->os($a);
        $this->assertSame($os1->id, $this->fazerChecklist($a, $tpl, $os1)->os_id, 'a OS do mesmo equipamento amarra');
        $this->assertNull($this->fazerChecklist($a, $tpl, $this->os($outroAtivo))->os_id, 'OS de outro equipamento e ignorada');
        $this->assertNull($this->fazerChecklist($a, $tpl, $osDeles)->os_id, 'OS de outro tenant e ignorada');
        $this->assertNull($this->fazerChecklist($a, $tpl, null)->os_id, 'sem OS (QR/Dossie) continua valendo');
    }

    public function test_a_os_mostra_feito_e_pendente_por_modelo(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);
        $tpl = $this->tpl('gerador-pre-uso');
        $svc = app(ChecklistsDaOs::class);

        $this->fazerChecklist($a, $tpl); // feito no equipamento, mas FORA desta OS
        $this->assertSame(1, $svc->paraOrdemServico($os)['pendentes'], 'checklist feito sem a OS nao conta para ela');
        $this->assertNull($svc->paraOrdemServico($os)['modelos'][0]['feito']);
        $this->assertSame(1, $svc->pendentesDaOs($os));

        $e = $this->fazerChecklist($a, $tpl, $os);
        $dados = $svc->paraOrdemServico($os);
        $this->assertSame(0, $dados['pendentes']);
        $this->assertSame($e->id, $dados['modelos'][0]['feito']->id);
        $this->assertSame(0, $svc->pendentesDaOs($os));
    }

    public function test_sem_as_chaves_do_contrato_nao_ha_pendencia(): void
    {
        [$off] = $this->novoTenant(false);
        $os = $this->os(Asset::factory()->create(['tenant_id' => $off->id, 'status' => Asset::STATUS_DISPONIVEL]));

        $this->assertSame(0, app(ChecklistsDaOs::class)->pendentesDaOs($os));
    }

    public function test_relatorio_e_exportacao_mostram_a_os_do_atendimento(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);
        $e = $this->fazerChecklist($a, $this->tpl('gerador-pre-uso'), $os);

        $this->assertContains('OS do atendimento', CkExecucaoExport::cabecalhoExecucoes());
        $caminho = CkExecucaoExport::xlsx(CkExecution::query()->whereKey($e->id));
        $reader = new Reader;
        $reader->open($caminho);
        $linhas = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            if ($sheet->getName() === 'Execuções') {
                foreach ($sheet->getRowIterator() as $row) {
                    $linhas[] = $row->toArray();
                }
            }
        }

        $reader->close();
        @unlink($caminho);
        $linha = array_combine($linhas[0], $linhas[1]);
        $this->assertSame((string) $os->os_number, (string) $linha['OS do atendimento']);
    }

    // ------------------------------------------------------------ Modo Campo

    public function test_a_etapa_do_checklist_mostra_o_checklist_pendente_com_o_botao_preencher(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);
        $tpl = $this->tpl('gerador-pre-uso');

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $os])->set('step', 2)
            ->assertSee('Checklist do equipamento')->assertSee('Checklist de pré-uso do gerador')->assertSee('Pendente')->assertSee('PREENCHER CHECKLIST')
            ->assertSee(e(route('ck.execucao.mobile', ['assetId' => $a->id, 'checklist' => $tpl->id, 'os' => $os->id])), false);
    }

    public function test_no_modo_campo_a_os_nao_conclui_sem_o_checklist_e_conclui_depois_de_feito(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a, ['status' => 'Em Andamento']);

        $c = Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $os])->set('step', 5)->call('next');
        $c->assertSet('step', 2)->assertHasErrors('checklist')->assertSee('Falta fazer o checklist do equipamento para concluir esta OS');
        $this->assertSame('Em Andamento', $os->fresh()->status, 'a OS nao concluiu');

        $this->fazerChecklist($a, $this->tpl('gerador-pre-uso'), $os);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $os->fresh()])->set('step', 5)->call('next')->assertHasNoErrors();
        $this->assertSame('Concluída', $os->fresh()->status, 'com o checklist feito nesta OS ela conclui');
    }

    public function test_quem_nao_pode_executar_checklist_nao_fica_preso_na_conclusao(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a, ['status' => 'Em Andamento']);
        $semPermissao = User::create(['name' => 'Tec', 'email' => 'tec-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $os->forceFill(['technician_id' => $semPermissao->id])->save();
        $this->actingAs($semPermissao);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $os->fresh()])->set('step', 5)->call('next');

        $this->assertSame('Concluída', $os->fresh()->status, 'sem permissao de checklist a OS nao fica sem saida');
    }

    public function test_em_contrato_sem_as_chaves_o_modo_campo_conclui_como_sempre(): void
    {
        [$off, $adminOff] = $this->novoTenant(false);
        $this->actingAs($adminOff);
        $os = $this->os(Asset::factory()->create(['tenant_id' => $off->id, 'status' => Asset::STATUS_DISPONIVEL]), ['status' => 'Em Andamento']);

        Livewire::test(MaintenanceOrderFieldWizard::class, ['maintenanceOrder' => $os])->set('step', 5)->call('next')->assertHasNoErrors('checklist');

        $this->assertSame('Concluída', $os->fresh()->status);
    }

    // ------------------------------------------------------------ impressao

    public function test_a_folha_impressa_da_os_traz_o_checklist_do_equipamento(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);

        $r = $this->get(route('maintenance-orders.folha', $os))->assertOk();

        $r->assertSee('Checklist de pré-uso do gerador')->assertSee('folha 1 de 2')->assertSee('CRÍTICO')->assertSee('class="caixa"', false)
            ->assertSee('Nível do óleo do motor dentro da marca')->assertSee('Assinatura de quem fez o checklist')->assertSee('Esta é a folha 1; as seguintes são o checklist do equipamento.')
            ->assertSee('Serviço executado'); // a folha da OS em si continua igual
        $this->assertSame(3, substr_count($r->getContent(), 'class="folha"'), 'folha da OS + 2 folhas do checklist (4 secoes + 19 itens passam de 20 linhas)');
        $this->assertSame(1, substr_count($r->getContent(), 'Assinatura de quem fez o checklist'), 'a assinatura so vai na ultima folha');
    }

    public function test_checklist_longo_vai_em_varias_folhas_sem_perder_nem_repetir_item(): void
    {
        $a = $this->ativo('caminhao');
        $os = $this->os($a);
        $tpl = $this->tpl('caminhao-saida');
        $total = app(ChecklistExecucaoService::class)->itensAtivos($tpl)->count();

        $r = $this->get(route('maintenance-orders.folha', $os))->assertOk();
        $html = $r->getContent();

        $this->assertGreaterThan(1, preg_match_all('/folha \d+ de (\d+)/', $html, $m) ? (int) $m[1][0] : 0, 'o checklist do caminhao (24 itens) ocupa mais de uma folha');
        $this->assertSame($total, substr_count($html, 'class="n">') - 0 > 0 ? preg_match_all('/<td class="n">\d+<\/td>/', $html) : 0, 'todos os itens aparecem, uma vez cada');
        $this->assertStringContainsString('Assinatura de quem fez o checklist', $html);
        $this->assertSame(1, substr_count($html, 'Assinatura de quem fez o checklist'), 'a assinatura so vai na ultima folha');
    }

    public function test_paginacao_nao_deixa_titulo_de_secao_sozinho_no_fim_e_repete_o_titulo_na_continuacao(): void
    {
        $a = $this->ativo('caminhao');
        $os = $this->os($a);

        $modelos = app(ChecklistsDaOs::class)->paraImpressao($os, 8);

        $paginas = $modelos[0]['paginas'];
        $this->assertGreaterThan(2, count($paginas));

        foreach ($paginas as $linhas) {
            $this->assertLessThanOrEqual(8, count($linhas));
            $this->assertSame('item', end($linhas)['tipo'], 'a pagina nunca termina em titulo de secao');
            $this->assertSame('secao', $linhas[0]['tipo'], 'toda pagina comeca com o titulo da secao (continuacao inclusive)');
        }

        $numeros = collect($paginas)->flatten(1)->where('tipo', 'item')->pluck('n')->all();
        $this->assertSame(range(1, count($numeros)), $numeros, 'numeracao continua e sem buracos entre as folhas');
    }

    public function test_sem_checklist_aplicavel_ou_sem_chave_a_folha_continua_como_antes(): void
    {
        $semGrupo = Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => null, 'checklist_group_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);
        $r = $this->get(route('maintenance-orders.folha', $this->os($semGrupo)))->assertOk();
        $this->assertSame(1, substr_count($r->getContent(), 'class="folha"'));
        $r->assertDontSee('Assinatura de quem fez o checklist');

        [$off, $adminOff] = $this->novoTenant(false);
        $this->actingAs($adminOff);
        $osOff = $this->os(Asset::factory()->create(['tenant_id' => $off->id, 'status' => Asset::STATUS_DISPONIVEL]));
        $this->assertSame(1, substr_count($this->get(route('maintenance-orders.folha', $osOff))->assertOk()->getContent(), 'class="folha"'));
    }

    public function test_a_folha_de_outro_tenant_continua_inacessivel(): void
    {
        [$outro] = $this->novoTenant(true);
        app(CatalogoInstalador::class)->instalar($outro);
        app(ChecklistInstalador::class)->instalar($outro, 'gerador');
        $osDeles = $this->os($this->ativo('gerador', $outro));

        $this->get(route('maintenance-orders.folha', $osDeles))->assertNotFound();
    }

    public function test_a_impressao_do_dossie_da_os_tambem_traz_o_checklist(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);

        $this->get(route('maintenance-orders.print', ['id' => $os->id]))->assertOk()
            ->assertSee('Checklist do equipamento: Checklist de pré-uso do gerador')->assertSee('Nível do óleo do motor dentro da marca')->assertSee('Assinatura de quem fez o checklist');
    }

    public function test_a_secao_da_os_no_painel_avisa_pendente_e_oferece_imprimir(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);

        $dados = app(ChecklistsDaOs::class)->paraOrdemServico($os);
        $html = view('filament.forms.checklists-da-os', ['dados' => $dados, 'os' => $os])->render();

        $this->assertStringContainsString('ainda não feito nesta OS', $html);
        $this->assertStringContainsString('Pendente nesta OS', $html);
        $this->assertStringContainsString(route('maintenance-orders.folha', $os), $html);
        $this->assertStringContainsString(route('ck.execucao.desktop', ['assetId' => $a->id, 'checklist' => $this->tpl('gerador-pre-uso')->id, 'os' => $os->id]), html_entity_decode($html));

        $this->fazerChecklist($a, $this->tpl('gerador-pre-uso'), $os);
        $depois = view('filament.forms.checklists-da-os', ['dados' => app(ChecklistsDaOs::class)->paraOrdemServico($os), 'os' => $os])->render();
        $this->assertStringNotContainsString('ainda não feito nesta OS', $depois);
        $this->assertStringContainsString('Feito em', $depois);
        $this->assertStringContainsString('Refazer', $depois);
    }

    public function test_um_item_so_aparece_na_folha_se_estiver_ativo(): void
    {
        $a = $this->ativo('gerador');
        $os = $this->os($a);
        $tpl = $this->tpl('gerador-pre-uso');
        $item = CkItem::withoutGlobalScopes()->whereIn('section_id', CkSection::withoutGlobalScopes()->where('template_id', $tpl->id)->pluck('id'))->where('slug_template', 'filtro-ar')->firstOrFail();
        $item->update(['ativo' => false]);

        $this->get(route('maintenance-orders.folha', $os))->assertOk()->assertDontSee($item->descricao)->assertSee('18 itens');
    }
}
