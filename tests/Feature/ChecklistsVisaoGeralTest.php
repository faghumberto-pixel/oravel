<?php

namespace Tests\Feature;

use App\Filament\Pages\VisaoGeralChecklists;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\CkExecutionResource;
use App\Filament\Resources\MaintenanceOrderResource;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\ChecklistGroup;
use App\Models\CkAnswer;
use App\Models\CkExecution;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Checklists\ChecklistAplicavel;
use App\Services\Checklists\ChecklistInstalador;
use App\Services\Checklists\ChecklistVisaoGeral;
use App\Support\CkVisaoGeralExport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * Visao geral dos checklists: cobertura por ativo, servicos x checklist e corretivas x checklist (o que o checklist dizia antes da corretiva).
 * SEGURANCA: so roda em banco com "test" no nome.
 */
class ChecklistsVisaoGeralTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    private ChecklistVisaoGeral $svc;

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
        $this->svc = app(ChecklistVisaoGeral::class);
    }

    /** @return array{0: Tenant, 1: User} */
    private function novoTenant(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [CatalogoItem::FEATURE_KEY => true, CkTemplate::FEATURE_KEY => true, 'tabela_ck_execucoes' => true, 'tabela_assets' => true, 'tabela_maintenance_orders' => true]]);
        $t = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'vg-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'Ana', 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return [$t, $u->refresh()];
    }

    private function ativo(string $nome, ?string $grupo = 'gerador', ?Tenant $t = null): Asset
    {
        $t ??= $this->tenant;
        $cat = $grupo ? AssetCategory::withoutGlobalScopes()->where('tenant_id', $t->id)->where('slug_template', $grupo)->firstOrFail() : null;

        return Asset::factory()->create(['tenant_id' => $t->id, 'name' => $nome, 'asset_category_id' => $cat?->id, 'checklist_group_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);
    }

    private function tpl(string $slug = 'gerador-pre-uso'): CkTemplate
    {
        return CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', $slug)->firstOrFail();
    }

    private function os(Asset $a, string $tipo, \DateTimeInterface|string $criada, array $extra = []): MaintenanceOrder
    {
        $os = MaintenanceOrder::create($extra + ['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'description' => 'x', 'maintenance_type' => $tipo, 'status' => 'Concluída', 'priority' => 'media']);
        $os->forceFill(['created_at' => $criada])->save();

        return $os->refresh();
    }

    /** Execucao com respostas [descricao => resposta] (ou [descricao => [resposta, obs]]). */
    private function execucao(Asset $a, \DateTimeInterface|string $quando, array $respostas = [], ?MaintenanceOrder $os = null, string $status = 'aprovado'): CkExecution
    {
        $e = CkExecution::withoutGlobalScopes()->create([
            'tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'template_id' => $this->tpl()->id, 'template_nome' => 'Checklist de pré-uso do gerador', 'versao_template' => 1,
            'executor_id' => $this->admin->id, 'status' => $status, 'iniciado_em' => $quando, 'finalizado_em' => $quando, 'os_id' => $os?->id,
        ]);

        foreach ($respostas as $item => $r) {
            [$resp, $obs] = is_array($r) ? $r : [$r, null];
            CkAnswer::withoutGlobalScopes()->create(['tenant_id' => $a->tenant_id, 'execution_id' => $e->id, 'item_descricao' => $item, 'secao_titulo' => 'Fluidos', 'tipo_resposta' => 'conforme_nao_conforme_na', 'critico' => false, 'resposta' => $resp, 'observacao' => $obs]);
        }

        return $e;
    }

    // ------------------------------------------------------------ regra SQL x regra do servico

    public function test_a_regra_em_sql_concorda_com_a_regra_do_servico_para_todo_tipo_de_ativo(): void
    {
        $gc = ChecklistGroup::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Geradores de obra']);
        $outroGc = ChecklistGroup::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Outro']);
        $this->tpl('empilhadeira-pre-uso')->update(['checklist_group_id' => $gc->id]); // so vale para o grupo de checklist
        $this->tpl('compressor-diario')->update(['ativo' => false]);
        $this->tpl('munk-pre-uso')->delete();

        $casos = [
            $this->ativo('gerador', 'gerador'),
            $this->ativo('empilhadeira sem gc', 'empilhadeira'),
            tap($this->ativo('empilhadeira com gc', 'empilhadeira'), fn ($a) => $a->update(['checklist_group_id' => $gc->id])),
            tap($this->ativo('empilhadeira outro gc', 'empilhadeira'), fn ($a) => $a->update(['checklist_group_id' => $outroGc->id])),
            $this->ativo('compressor (modelo inativo)', 'compressor'),
            $this->ativo('munk (modelo arquivado)', 'munk'),
            $this->ativo('sem grupo nenhum', null),
            tap($this->ativo('so gc', null), fn ($a) => $a->update(['checklist_group_id' => $gc->id])),
        ];

        $com = $this->svc->queryAtivos([])->pluck('id');
        $filtro = app(ChecklistAplicavel::class);
        $comSql = $filtro->filtrarAtivosComChecklist(Asset::query())->pluck('id');
        $semSql = $filtro->filtrarAtivosComChecklist(Asset::query(), false)->pluck('id');

        foreach ($casos as $a) {
            $esperado = $filtro->paraAtivo($a->fresh())->isNotEmpty();
            $this->assertSame($esperado, $comSql->contains($a->id), "SQL 'com' diverge do servico para: {$a->name}");
            $this->assertSame(! $esperado, $semSql->contains($a->id), "SQL 'sem' diverge do servico para: {$a->name}");
        }

        $this->assertCount(count($casos), $com);
    }

    // ------------------------------------------------------------ 1. cobertura

    public function test_cobertura_separa_sem_checklist_nunca_feito_em_dia_e_atrasado(): void
    {
        $sem = $this->ativo('Ativo sem grupo', null);
        $nunca = $this->ativo('Gerador nunca', 'gerador');
        $emDia = $this->ativo('Gerador em dia', 'gerador');
        $atrasado = $this->ativo('Gerador atrasado', 'gerador');
        $this->execucao($emDia, now()->subDays(5));
        $this->execucao($atrasado, now()->subDays(40));

        $ids = fn (string $s) => $this->svc->queryAtivos(['status' => $s])->pluck('id')->all();

        $this->assertSame([$sem->id], $ids('sem_checklist'));
        $this->assertSame([$nunca->id], $ids('nunca'));
        $this->assertSame([$emDia->id], $ids('em_dia'));
        $this->assertSame([$atrasado->id], $ids('atrasado'));
        $this->assertSame(['total' => 4, 'sem_checklist' => 1, 'nunca' => 1, 'atrasado' => 1, 'em_dia' => 1], $this->svc->resumoCobertura([]));
        $this->assertSame([$emDia->id, $atrasado->id], $this->svc->queryAtivos(['status' => 'atrasado', 'dias' => 3])->orderBy('name')->pluck('id')->sort()->values()->all() === [] ? [] : [$emDia->id, $atrasado->id], 'com limite menor, o em dia tambem passa a atrasado');
        $this->assertEqualsCanonicalizing([$emDia->id, $atrasado->id], $this->svc->queryAtivos(['status' => 'atrasado', 'dias' => 3])->pluck('id')->all());

        $linha = $this->svc->linhaDoAtivo($atrasado->load('category'), []);
        $this->assertSame('atrasado', $linha['status']);
        $this->assertSame(40, $linha['dias_desde']);
        $this->assertSame(['Checklist de pré-uso do gerador'], $linha['modelos']);
        $this->assertSame('sem_checklist', $this->svc->linhaDoAtivo($sem, [])['status']);
    }

    public function test_cobertura_filtra_por_grupo_e_conta_as_os_com_checklist(): void
    {
        $g = $this->ativo('Gerador A', 'gerador');
        $c = $this->ativo('Compressor A', 'compressor');
        $osComChecklist = $this->os($g, 'Preventiva', now()->subDays(10));
        $this->os($g, 'Corretiva', now()->subDays(8));
        $this->execucao($g, now()->subDays(10), [], $osComChecklist);

        $this->assertSame([$g->id], $this->svc->queryAtivos(['grupo' => $g->asset_category_id])->pluck('id')->all());
        $linha = $this->svc->linhaDoAtivo($g->load('category'), ['de' => now()->subDays(30)->toDateString()]);
        $this->assertSame(2, $linha['os_no_periodo']);
        $this->assertSame(1, $linha['os_com_checklist']);
        $this->assertSame(0, $this->svc->linhaDoAtivo($c->load('category'), [])['os_no_periodo']);
    }

    // ------------------------------------------------------------ 2. servicos

    public function test_servicos_mostram_o_checklist_feito_pendente_ou_sem_checklist_aplicavel(): void
    {
        $g = $this->ativo('Gerador', 'gerador');
        $sem = $this->ativo('Sem grupo', null);
        $feita = $this->os($g, 'Preventiva', now()->subDays(3));
        $pendente = $this->os($g, 'Corretiva', now()->subDays(2));
        $semModelo = $this->os($sem, 'Preventiva', now()->subDays(2));
        $this->os($g, 'Check-in', now()->subDay()); // nao e servico de manutencao
        $this->execucao($g, now()->subDays(3), [], $feita);

        $this->assertEqualsCanonicalizing([$feita->id, $pendente->id, $semModelo->id], $this->svc->queryServicos([])->pluck('id')->all());
        $this->assertSame([$feita->id], $this->svc->queryServicos(['checklist' => 'feito'])->pluck('id')->all());
        $this->assertSame([$pendente->id], $this->svc->queryServicos(['checklist' => 'pendente'])->pluck('id')->all());
        $this->assertSame([$semModelo->id], $this->svc->queryServicos(['checklist' => 'sem_modelo'])->pluck('id')->all());
        $this->assertSame(['total' => 3, 'feito' => 1, 'pendente' => 1, 'sem_modelo' => 1], $this->svc->resumoServicos([]));
        $this->assertSame([$pendente->id], $this->svc->queryServicos(['tipo' => 'Corretiva'])->pluck('id')->all());
        $this->assertSame('feito', $this->svc->linhaDaOs($feita->load('asset'))['situacao']);
        $this->assertSame('pendente', $this->svc->linhaDaOs($pendente->load('asset'))['situacao']);
        $this->assertSame('sem_modelo', $this->svc->linhaDaOs($semModelo->load('asset'))['situacao']);

        $this->assertSame([$feita->id], $this->svc->queryServicos(['de' => now()->subDays(4)->toDateString(), 'ate' => now()->subDays(3)->toDateString()])->pluck('id')->all(), 'periodo');
    }

    // ------------------------------------------------------------ 3. corretivas x checklist

    public function test_corretiva_de_equipamento_sem_checklist_nao_tem_o_que_comparar(): void
    {
        $sem = $this->ativo('Sem grupo', null);
        $c = $this->os($sem, 'Corretiva', now(), ['description' => 'Parou de funcionar']);

        $a = $this->svc->analisarCorretiva($c->load('asset'));

        $this->assertSame('sem_checklist', $a['diagnostico']);
        $this->assertNull($a['ultimo_checklist']);
        $this->assertFalse($a['tinha_modelo']);
    }

    public function test_corretiva_de_equipamento_com_checklist_que_nunca_foi_feito(): void
    {
        $g = $this->ativo('Gerador', 'gerador');
        $c = $this->os($g, 'Corretiva', now(), ['description' => 'Vazamento de óleo']);

        $a = $this->svc->analisarCorretiva($c->load('asset'));

        $this->assertSame('nunca_conferido', $a['diagnostico']);
        $this->assertTrue($a['tinha_modelo']);
    }

    public function test_item_relacionado_conferido_como_conforme_indica_falha_subita_ou_conferencia_superficial(): void
    {
        $g = $this->ativo('Gerador', 'gerador');
        $this->execucao($g, now()->subDays(12), ['Nível do óleo do motor dentro da marca' => 'conforme', 'Extintor presente, carregado e dentro da validade' => 'conforme']);
        $c = $this->os($g, 'Corretiva', now(), ['description' => 'Motor fundiu por falta de óleo']);

        $a = $this->svc->analisarCorretiva($c->load('asset'));

        $this->assertSame('conferido', $a['diagnostico']);
        $this->assertSame(12, $a['dias_antes']);
        $this->assertCount(1, $a['relacionados'], 'so o item do oleo tem relacao com o problema');
        $this->assertSame('Nível do óleo do motor dentro da marca', $a['relacionados'][0]['item']);
        $this->assertSame('conforme', $a['relacionados'][0]['resposta']);
        $this->assertSame(['motor', 'oleo'], $a['relacionados'][0]['palavras']);
    }

    public function test_problema_que_ja_estava_nao_conforme_no_checklist_vem_primeiro_e_vira_ja_registrado(): void
    {
        $g = $this->ativo('Gerador', 'gerador');
        $this->execucao($g, now()->subDays(20), [
            'Nível do óleo do motor dentro da marca' => 'conforme',
            'Sem vazamento de óleo, combustível ou líquido de arrefecimento' => ['nao_conforme', 'mancha de óleo sob o motor'],
        ], null, 'reprovado');
        $c = $this->os($g, 'Corretiva', now(), ['description' => 'Vazando óleo pela junta']);

        $a = $this->svc->analisarCorretiva($c->load('asset'));

        $this->assertSame('ja_registrado', $a['diagnostico']);
        $this->assertSame('nao_conforme', $a['relacionados'][0]['resposta'], 'o NAO CONFORME vem antes do conforme');
        $this->assertContains('vazando', $a['relacionados'][0]['palavras']);
        $this->assertSame('mancha de óleo sob o motor', $a['relacionados'][0]['observacao']);
    }

    public function test_problema_sem_item_no_checklist_sugere_incluir_um_item(): void
    {
        $g = $this->ativo('Gerador', 'gerador');
        $this->execucao($g, now()->subDays(5), ['Nível do óleo do motor dentro da marca' => 'conforme']);
        $c = $this->os($g, 'Corretiva', now(), ['description' => 'Rolamento do alternador travou']);

        $a = $this->svc->analisarCorretiva($c->load('asset'));

        $this->assertSame('sem_item', $a['diagnostico']);
        $this->assertSame([], $a['relacionados']);
    }

    public function test_so_conta_o_checklist_feito_antes_da_corretiva_e_acha_a_ultima_os_e_o_checklist_dela(): void
    {
        $g = $this->ativo('Gerador', 'gerador');
        $antiga = $this->os($g, 'Preventiva', now()->subDays(30));
        $ultimaOs = $this->os($g, 'Preventiva', now()->subDays(10));
        $this->execucao($g, now()->subDays(30), ['Nível do óleo do motor dentro da marca' => 'conforme'], $antiga);
        $naUltima = $this->execucao($g, now()->subDays(10), ['Nível do óleo do motor dentro da marca' => ['nao_conforme', 'baixo']], $ultimaOs, 'reprovado');
        $c = $this->os($g, 'Corretiva', now()->subDays(2), ['description' => 'Óleo baixo, motor travou']);
        $this->execucao($g, now(), ['Nível do óleo do motor dentro da marca' => 'conforme']); // DEPOIS da corretiva: nao conta

        $a = $this->svc->analisarCorretiva($c->load('asset'));

        $this->assertSame($ultimaOs->id, $a['ultima_os']->id);
        $this->assertSame($naUltima->id, $a['checklist_na_ultima_os']->id);
        $this->assertSame($naUltima->id, $a['ultimo_checklist']->id);
        $this->assertSame(8, $a['dias_antes']);
        $this->assertSame('ja_registrado', $a['diagnostico']);
    }

    public function test_ultima_os_sem_checklist_e_sinalizada(): void
    {
        $g = $this->ativo('Gerador', 'gerador');
        $ultima = $this->os($g, 'Preventiva', now()->subDays(6));
        $c = $this->os($g, 'Corretiva', now(), ['description' => 'x']);

        $a = $this->svc->analisarCorretiva($c->load('asset'));

        $this->assertSame($ultima->id, $a['ultima_os']->id);
        $this->assertNull($a['checklist_na_ultima_os'], 'a ultima OS rodou sem o checklist');
        $this->assertTrue($a['tinha_modelo']);
    }

    public function test_resumo_e_lista_de_corretivas_filtram_por_diagnostico(): void
    {
        $g1 = $this->ativo('Gerador 1', 'gerador');
        $g2 = $this->ativo('Gerador 2', 'gerador');
        $sem = $this->ativo('Sem grupo', null);
        $this->execucao($g1, now()->subDays(3), ['Nível do óleo do motor dentro da marca' => 'conforme']);
        $c1 = $this->os($g1, 'Corretiva', now(), ['description' => 'Falta de óleo']);
        $c2 = $this->os($g2, 'Emergência', now(), ['description' => 'Parou']);
        $c3 = $this->os($sem, 'Corretiva', now(), ['description' => 'Parou']);
        $this->os($g1, 'Preventiva', now()); // nao e corretiva

        $r = $this->svc->resumoCorretivas([]);

        $this->assertSame(['total' => 3, 'sem_checklist' => 1, 'nunca_conferido' => 1, 'ja_registrado' => 0, 'conferido' => 1, 'sem_item' => 0], $r);
        $this->assertEqualsCanonicalizing([$c1->id], $this->svc->corretivas([], 'conferido')->getCollection()->pluck('os.id')->all());
        $this->assertEqualsCanonicalizing([$c1->id, $c2->id, $c3->id], $this->svc->corretivas([])->getCollection()->pluck('os.id')->all());
    }

    // ------------------------------------------------------------ palavras

    public function test_o_casamento_de_palavras_ignora_genericas_acentos_numeros_e_aceita_plural_e_sinonimos(): void
    {
        $r = fn (string $t) => array_keys($this->svc->radicais($t));

        $this->assertSame($r('vazamento'), $r('Vazamentos'));
        $this->assertSame($r('óleo'), $r('OLEO'));
        $this->assertSame([], $r('Verificar o nível do equipamento, estado geral 2024'), 'so palavras genericas, preposicao e numero');
        $this->assertSame($r('arrefecimento'), $r('radiador'), 'sinonimo de campo');
        $this->assertContains('correi', $r('correia'));
        $this->assertNotSame($r('correia'), $r('corrente'), 'correia e corrente nao se confundem');
    }

    // ------------------------------------------------------------ a pagina

    public function test_a_pagina_lista_so_do_tenant_e_o_cartao_filtra_e_cada_linha_abre_o_destino(): void
    {
        $sem = $this->ativo('Ativo sem checklist', null);
        $com = $this->ativo('Gerador com checklist', 'gerador');
        [$outro] = $this->novoTenant();
        app(CatalogoInstalador::class)->instalar($outro);
        $deles = $this->ativo('Gerador do vizinho', 'gerador', $outro);

        $c = Livewire::test(VisaoGeralChecklists::class)
            ->assertSee('Ativo sem checklist')->assertSee('Gerador com checklist')->assertDontSee('Gerador do vizinho')
            ->assertSee(e(AssetResource::getUrl('edit', ['record' => $sem])), false);

        $c->call('filtrarCobertura', 'sem_checklist')->assertSet('status', 'sem_checklist')->assertSee('Ativo sem checklist')->assertDontSee('Gerador com checklist');
        $c->call('filtrarCobertura', 'sem_checklist')->assertSet('status', null)->assertSee('Gerador com checklist');
        $this->assertNotNull($deles);
    }

    public function test_aba_servicos_mostra_a_os_com_link_e_o_cartao_filtra(): void
    {
        $g = $this->ativo('Gerador', 'gerador');
        $feita = $this->os($g, 'Preventiva', now()->subDays(2));
        $pendente = $this->os($g, 'Corretiva', now()->subDay());
        $e = $this->execucao($g, now()->subDays(2), [], $feita);

        $c = Livewire::test(VisaoGeralChecklists::class)->call('irParaAba', 'servicos')
            ->assertSee($feita->os_number)->assertSee($pendente->os_number)->assertSee('Checklist feito')->assertSee('Checklist pendente')
            ->assertSee(e(MaintenanceOrderResource::getUrl('edit', ['record' => $feita])), false)
            ->assertSee(e(CkExecutionResource::getUrl('view', ['record' => $e])), false);

        $c->call('filtrarServicos', 'pendente')->assertSet('checklist', 'pendente')->assertSee($pendente->os_number)->assertDontSee($feita->os_number);
    }

    public function test_aba_corretivas_explica_o_que_o_checklist_dizia_antes(): void
    {
        $g = $this->ativo('Gerador', 'gerador');
        $e = $this->execucao($g, now()->subDays(9), ['Nível do óleo do motor dentro da marca' => 'conforme']);
        $corretiva = $this->os($g, 'Corretiva', now(), ['description' => 'Motor fundiu por falta de óleo']);

        Livewire::test(VisaoGeralChecklists::class)->call('irParaAba', 'corretivas')
            ->assertSee($corretiva->os_number)->assertSee('Conferido sem problema')->assertSee('Nível do óleo do motor dentro da marca')->assertSee('Conforme')->assertSee('palavras em comum: motor, oleo')
            ->assertSee('9 dias antes')->assertSee(e(CkExecutionResource::getUrl('view', ['record' => $e])), false)
            ->call('filtrarCorretivas', 'ja_registrado')->assertDontSee($corretiva->os_number)->call('filtrarCorretivas', 'conferido')->assertSee($corretiva->os_number);
    }

    public function test_acesso_so_com_permissao_e_chave_do_contrato(): void
    {
        $semPermissao = User::create(['name' => 'S', 'email' => 's-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->actingAs($semPermissao);
        $this->assertFalse(VisaoGeralChecklists::canAccess());
        $this->assertFalse(VisaoGeralChecklists::shouldRegisterNavigation());
        Livewire::test(VisaoGeralChecklists::class)->assertForbidden();

        $this->actingAs($this->admin);
        $this->assertTrue(VisaoGeralChecklists::canAccess());
    }

    public function test_exportacao_traz_as_tres_perguntas_com_os_filtros_da_tela(): void
    {
        $g = $this->ativo('Gerador', 'gerador');
        $sem = $this->ativo('Sem grupo', null);
        $this->execucao($g, now()->subDays(9), ['Nível do óleo do motor dentro da marca' => 'conforme']);
        $os = $this->os($g, 'Corretiva', now(), ['description' => 'Motor fundiu por falta de óleo']);

        $lerAba = function (string $aba, array $f = []) {
            $caminho = CkVisaoGeralExport::xlsx($aba, $f, $this->svc);
            $reader = new Reader;
            $reader->open($caminho);
            $out = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $out[$sheet->getName()][] = $row->toArray();
                }
            }

            $reader->close();
            @unlink($caminho);

            return $out;
        };

        $cob = $lerAba('cobertura');
        $this->assertSame('Cobertura', array_key_first($cob));
        $this->assertCount(3, $cob['Cobertura'], 'cabecalho + 2 ativos');
        $this->assertContains('Sem checklist aplicável', array_column($cob['Cobertura'], 4));
        $this->assertCount(2, $lerAba('cobertura', ['status' => 'sem_checklist'])['Cobertura'], 'respeita o filtro: cabecalho + o ativo sem checklist');

        $serv = $lerAba('servicos');
        $this->assertSame('Serviços', array_key_first($serv));
        $this->assertSame($os->os_number, (string) $serv['Serviços'][1][0]);
        $this->assertSame('Pendente', $serv['Serviços'][1][6]);

        $cor = $lerAba('corretivas');
        $this->assertSame('Corretivas x checklist', array_key_first($cor));
        $linha = array_combine($cor['Corretivas x checklist'][0], $cor['Corretivas x checklist'][1]);
        $this->assertSame($os->os_number, (string) $linha['OS corretiva']);
        $this->assertStringContainsString('Item relacionado foi conferido sem problema', $linha['Diagnóstico']);
        $this->assertStringContainsString('Nível do óleo do motor dentro da marca [conforme; palavras: motor, oleo]', $linha['Itens relacionados']);
        $this->assertNotNull($sem);
    }

    public function test_botao_exportar_baixa_o_arquivo_da_aba_aberta(): void
    {
        $this->ativo('Gerador', 'gerador');

        Livewire::test(VisaoGeralChecklists::class)->callAction('exportar')->assertFileDownloaded();
        Livewire::test(VisaoGeralChecklists::class)->call('irParaAba', 'corretivas')->callAction('exportar')->assertFileDownloaded();
    }
}
