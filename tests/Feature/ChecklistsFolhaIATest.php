<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource\Pages\AtualizarComFolha;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDowntimeEvent;
use App\Models\CatalogoItem;
use App\Models\CkAnswer;
use App\Models\CkExecution;
use App\Models\CkTemplate;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Checklists\ChecklistFolhaIA;
use App\Services\Checklists\ChecklistInstalador;
use App\Services\Checklists\ChecklistsDaOs;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Checklist em PAPEL lido pela IA (com a IA simulada): le a folha, a pessoa confere, e so entao registra, amarrado a OS, com a foto da folha como
 * evidencia e as mesmas regras de item critico da tela. SEGURANCA: so roda em banco com "test" no nome.
 */
class ChecklistsFolhaIATest extends TestCase
{
    use DatabaseTransactions;

    private const FOTO = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }

        config(['services.anthropic.key' => 'chave-de-teste']);
        Storage::fake('local');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [CatalogoItem::FEATURE_KEY => true, CkTemplate::FEATURE_KEY => true, 'tabela_ck_execucoes' => true, 'tabela_assets' => true, 'tabela_maintenance_orders' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'ia-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = User::create(['name' => 'Ana Gestora', 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        app(CatalogoInstalador::class)->instalar($this->tenant);
        app(ChecklistInstalador::class)->instalar($this->tenant);
        $this->actingAs($this->admin);
    }

    private function ativo(string $grupo = 'gerador'): Asset
    {
        $cat = AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', $grupo)->firstOrFail();

        return Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => $cat->id, 'status' => Asset::STATUS_DISPONIVEL]);
    }

    private function os(Asset $a): MaintenanceOrder
    {
        return MaintenanceOrder::create(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'description' => 'x', 'maintenance_type' => 'Preventiva', 'status' => 'Em Andamento', 'priority' => 'media']);
    }

    private function tpl(): CkTemplate
    {
        return CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', 'gerador-pre-uso')->firstOrFail();
    }

    private function numero(string $slug): int
    {
        foreach (app(ChecklistFolhaIA::class)->itensNumerados($this->tpl()) as $n => $item) {
            if ($item->slug_template === $slug) {
                return $n;
            }
        }

        $this->fail("item {$slug} nao existe");
    }

    /** IA simulada: tudo ok, exceto o que for passado (slug => linha). */
    private function fakeIA(array $trocas = [], ?string $horimetro = '1250'): void
    {
        $linhas = [];

        foreach (app(ChecklistFolhaIA::class)->itensNumerados($this->tpl()) as $n => $item) {
            $linhas[$n] = match ($item->tipo_resposta) {
                'numerico' => ['n' => $n, 'marcacao' => 'vazio', 'valor' => '10,5 V', 'obs' => '', 'duvida' => false],
                'selecao' => ['n' => $n, 'marcacao' => 'vazio', 'valor' => (string) ($item->opcoes[0] ?? ''), 'obs' => '', 'duvida' => false],
                'texto' => ['n' => $n, 'marcacao' => 'vazio', 'valor' => 'ok', 'obs' => '', 'duvida' => false],
                default => ['n' => $n, 'marcacao' => 'ok', 'valor' => '', 'obs' => '', 'duvida' => false],
            };
        }

        foreach ($trocas as $slug => $linha) {
            $n = $this->numero($slug);
            $linhas[$n] = ['n' => $n] + $linha + ['valor' => '', 'obs' => '', 'duvida' => false];
        }

        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['itens' => array_values($linhas), 'horimetro_km' => $horimetro])]]], 200)]);
    }

    private function conferido(CkTemplate $tpl, array $trocas = []): array
    {
        $r = [];

        foreach (app(ChecklistFolhaIA::class)->itensNumerados($tpl) as $item) {
            $r[$item->id] = match ($item->tipo_resposta) {
                'numerico' => ['resposta' => null, 'valor' => '10.5', 'obs' => null], 'selecao' => ['resposta' => null, 'valor' => $item->opcoes[0], 'obs' => null],
                'texto' => ['resposta' => null, 'valor' => 'ok', 'obs' => null], default => ['resposta' => 'conforme', 'valor' => null, 'obs' => null],
            };
        }

        foreach ($trocas as $slug => $linha) {
            $r[app(ChecklistFolhaIA::class)->itensNumerados($tpl)[$this->numero($slug)]->id] = $linha + ['resposta' => null, 'valor' => null, 'obs' => null];
        }

        return $r;
    }

    // ------------------------------------------------------------ leitura

    public function test_a_ia_recebe_a_folha_e_a_lista_numerada_e_devolve_o_que_leu_sem_gravar_nada(): void
    {
        $this->fakeIA(['nivel-oleo' => ['marcacao' => 'nc', 'obs' => 'óleo baixo'], 'extintor' => ['marcacao' => 'na'], 'tensao-saida' => ['marcacao' => 'vazio', 'valor' => '220,5 V'], 'sinalizacao' => ['marcacao' => 'vazio', 'duvida' => true]]);

        $r = app(ChecklistFolhaIA::class)->ler($this->tpl(), self::FOTO);

        $this->assertTrue($r['ok']);
        $itens = collect(app(ChecklistFolhaIA::class)->itensNumerados($this->tpl()))->keyBy(fn ($i) => $i->slug_template);
        $this->assertSame(['resposta' => 'nao_conforme', 'valor' => null, 'obs' => 'óleo baixo', 'duvida' => false], $r['itens'][$itens['nivel-oleo']->id]);
        $this->assertSame('na', $r['itens'][$itens['extintor']->id]['resposta']);
        $this->assertSame('220.5', $r['itens'][$itens['tensao-saida']->id]['valor']);
        $this->assertNull($r['itens'][$itens['sinalizacao']->id]['resposta'], 'sem marca nunca vira resposta');
        $this->assertTrue($r['itens'][$itens['sinalizacao']->id]['duvida']);
        $this->assertSame('1250', $r['leitura']);
        $this->assertSame(0, CkExecution::withoutGlobalScopes()->count(), 'ler nao grava nada');

        Http::assertSent(function ($req) {
            $corpo = json_encode($req->data(), JSON_UNESCAPED_UNICODE);

            return str_contains($corpo, '1. Nível do óleo do motor') && str_contains($corpo, '"type":"image"') && str_contains($corpo, 'NUNCA adivinhe') && str_contains($corpo, 'Checklist de pré-uso do gerador');
        });
    }

    public function test_marcacao_invalida_opcao_inexistente_e_numero_ilegivel_ficam_em_branco_para_a_conferencia(): void
    {
        $tplVl = CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', 'veiculo-leve-saida')->firstOrFail();
        $combustivel = null;
        $n = 0;

        foreach (app(ChecklistFolhaIA::class)->itensNumerados($tplVl) as $num => $item) {
            if ($item->slug_template === 'combustivel') {
                $combustivel = $item;
                $n = $num;
            }
        }
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode(['itens' => [
            ['n' => $n, 'marcacao' => 'vazio', 'valor' => 'Meio tanque inventado'], ['n' => 999, 'marcacao' => 'ok'], ['n' => 1, 'marcacao' => 'talvez'],
        ]])]]], 200)]);

        $r = app(ChecklistFolhaIA::class)->ler($tplVl, self::FOTO);

        $this->assertNull($r['itens'][$combustivel->id]['valor'], 'opcao que nao existe no modelo e descartada');
        $this->assertCount(2, $r['itens'], 'numero fora do checklist e ignorado');
        $primeiro = collect(app(ChecklistFolhaIA::class)->itensNumerados($tplVl))->first();
        $this->assertNull($r['itens'][$primeiro->id]['resposta']);
        $this->assertTrue($r['itens'][$primeiro->id]['duvida'], 'marca estranha vai marcada como duvida');
    }

    public function test_falhas_da_ia_voltam_como_erro_sem_derrubar_nada(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'isto nao e json']]], 200)]);
        $this->assertFalse(app(ChecklistFolhaIA::class)->ler($this->tpl(), self::FOTO)['ok']);

        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'sobrecarga']], 529)]);
        $erro = app(ChecklistFolhaIA::class)->ler($this->tpl(), self::FOTO);
        $this->assertFalse($erro['ok']);
        $this->assertNotEmpty($erro['erro']);

        Http::fake();
        $r = app(ChecklistFolhaIA::class)->ler($this->tpl(), 'isto nao e uma foto');
        $this->assertFalse($r['ok']);
        Http::assertNothingSent();
    }

    // ------------------------------------------------------------ registro

    public function test_registrar_grava_o_checklist_do_papel_na_os_com_a_foto_da_folha_e_as_regras_do_item_critico(): void
    {
        $a = $this->ativo();
        $os = $this->os($a);
        $tpl = $this->tpl();

        $e = app(ChecklistFolhaIA::class)->registrar($a, $tpl, $this->conferido($tpl, ['nivel-oleo' => ['resposta' => 'nao_conforme', 'obs' => 'óleo baixo']]), [self::FOTO], $os, '1250', 'folha 1', $this->admin);

        $this->assertSame(CkExecution::STATUS_REPROVADO, $e->status);
        $this->assertSame(CkExecution::ORIGEM_PAPEL_IA, $e->registro_origem);
        $this->assertSame('Papel (IA)', $e->origemRotulo());
        $this->assertSame($os->id, $e->os_id, 'registrado contra a OS');
        $this->assertNull($e->assinatura, 'a assinatura esta na folha de papel');
        $this->assertStringContainsString('lida pela IA e conferida por Ana Gestora', (string) $e->observacoes);
        $this->assertStringContainsString('folha 1', (string) $e->observacoes);
        $this->assertCount(1, $e->folhas);
        Storage::disk('local')->assertExists($e->folhas[0]);
        $this->assertStringStartsWith('ck-execucoes/'.$this->tenant->id.'/folhas/', $e->folhas[0]);

        $oleo = CkAnswer::withoutGlobalScopes()->where('execution_id', $e->id)->where('resposta', 'nao_conforme')->firstOrFail();
        $this->assertSame($e->folhas, $oleo->fotos, 'a evidencia do item nao conforme e a foto da folha');

        $this->assertSame('inapto', $a->fresh()->aptidao_status, 'item critico lido do papel reprova como na tela');
        $corretiva = MaintenanceOrder::withoutGlobalScopes()->findOrFail($e->ordem_servico_id);
        $this->assertSame(MaintenanceOrder::TYPE_CORRECTIVE, $corretiva->maintenance_type);
        $this->assertTrue(AssetDowntimeEvent::withoutGlobalScopes()->where('maintenance_order_id', $corretiva->id)->exists());
        $this->assertCount(1, $corretiva->getMedia('checklist_evidencias'), 'a foto da folha vai para a OS corretiva');
        $this->assertSame(0, app(ChecklistsDaOs::class)->pendentesDaOs($os), 'o checklist deixa de estar pendente nesta OS');
    }

    public function test_registrar_exige_a_conferencia_completa_e_a_foto_da_folha(): void
    {
        $a = $this->ativo();
        $os = $this->os($a);
        $tpl = $this->tpl();
        $semResposta = $this->conferido($tpl);
        $semResposta[app(ChecklistFolhaIA::class)->itensNumerados($tpl)[$this->numero('nivel-oleo')]->id] = ['resposta' => null, 'valor' => null, 'obs' => null];

        try {
            app(ChecklistFolhaIA::class)->registrar($a, $tpl, $semResposta, [self::FOTO], $os, null, null, $this->admin);
            $this->fail('registrou com item obrigatorio sem resposta');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Responda o item: Nível do óleo', $e->errors()['itens'][0]);
        }

        try {
            app(ChecklistFolhaIA::class)->registrar($a, $tpl, $this->conferido($tpl), [], $os, null, null, $this->admin);
            $this->fail('registrou sem a foto da folha');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('folhas', $e->errors());
        }

        $this->assertSame(0, CkExecution::withoutGlobalScopes()->count(), 'nada foi gravado');
    }

    // ------------------------------------------------------------ a pagina da OS (foto -> IA -> conferir -> registrar)

    public function test_na_pagina_da_os_a_pessoa_fotografa_confere_e_registra(): void
    {
        $a = $this->ativo();
        $os = $this->os($a);
        $tpl = $this->tpl();
        $this->fakeIA(['filtro-ar' => ['marcacao' => 'nc', 'obs' => 'sujo'], 'sinalizacao' => ['marcacao' => 'vazio', 'duvida' => true]]);

        $c = Livewire::test(AtualizarComFolha::class, ['record' => $os->id])
            ->assertSee('Checklist em papel')->assertSee('ainda não feito nesta O.S.')
            ->set('ckTemplateId', $tpl->id)
            ->set('ckData.foto', self::FOTO)
            ->call('lerChecklistComIA');

        $linhas = collect($c->get('ckLinhas'));
        $this->assertCount(app(ChecklistFolhaIA::class)->itensNumerados($tpl) ? count(app(ChecklistFolhaIA::class)->itensNumerados($tpl)) : 0, $linhas);
        $this->assertCount(1, $c->get('ckFolhas'));
        $this->assertSame('1250', $c->get('ckLeitura'));
        $c->assertSee('Confira o que a IA leu')->assertSee('⚠ conferir');

        // a pessoa conferiu: so falta resolver o item em duvida
        $idDuvida = $linhas->search(fn ($l) => $l['descricao'] === 'Sinalização de risco elétrico e área delimitada');
        $c->call('registrarChecklistLido')->assertNotified('Falta conferir o checklist');
        $this->assertSame(0, CkExecution::withoutGlobalScopes()->count(), 'sem conferir nao grava');

        $c->set("ckLinhas.{$idDuvida}.resposta", 'conforme')->call('registrarChecklistLido')->assertRedirect();

        $e = CkExecution::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($os->id, $e->os_id);
        $this->assertSame(CkExecution::ORIGEM_PAPEL_IA, $e->registro_origem);
        $this->assertSame(CkExecution::STATUS_APROVADO_COM_RESSALVA, $e->status, 'so item nao critico com problema: aprovado com ressalva');
        $this->assertSame(1, CkAnswer::withoutGlobalScopes()->where('execution_id', $e->id)->pendencias()->count());
        $this->assertSame(0, app(ChecklistsDaOs::class)->pendentesDaOs($os));
    }

    public function test_um_checklist_de_varias_folhas_soma_as_leituras_sem_apagar_as_anteriores(): void
    {
        $a = $this->ativo();
        $os = $this->os($a);
        $tpl = $this->tpl();
        $itens = app(ChecklistFolhaIA::class)->itensNumerados($tpl);
        $nOleo = $this->numero('nivel-oleo');
        $nFiltro = $this->numero('filtro-ar');

        $c = Livewire::test(AtualizarComFolha::class, ['record' => $os->id])->set('ckTemplateId', $tpl->id);

        Http::fake(['api.anthropic.com/*' => Http::sequence()->push(['content' => [['type' => 'text', 'text' => json_encode(['itens' => [['n' => $nOleo, 'marcacao' => 'ok']]])]]], 200)
            ->push(['content' => [['type' => 'text', 'text' => json_encode(['itens' => [['n' => $nFiltro, 'marcacao' => 'nc', 'obs' => 'sujo']]])]]], 200)]);
        $c->set('ckData.foto', self::FOTO)->call('lerChecklistComIA');
        $c->set('ckData.foto', self::FOTO)->call('lerChecklistComIA');

        $linhas = $c->get('ckLinhas');
        $this->assertSame('conforme', $linhas[$itens[$nOleo]->id]['resposta'], 'a leitura da folha 1 continua');
        $this->assertSame('nao_conforme', $linhas[$itens[$nFiltro]->id]['resposta'], 'a folha 2 soma');
        $this->assertCount(2, $c->get('ckFolhas'), 'as duas fotos viram evidencia');
    }

    public function test_a_secao_so_aparece_com_checklist_aplicavel_e_permissao(): void
    {
        $semGrupo = Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => null, 'checklist_group_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);
        Livewire::test(AtualizarComFolha::class, ['record' => $this->os($semGrupo)->id])->assertDontSee('Checklist em papel');

        $this->actingAs($this->admin);
        Livewire::test(AtualizarComFolha::class, ['record' => $this->os($this->ativo())->id])->assertSee('Checklist em papel');
    }

    public function test_sem_foto_ou_sem_escolher_o_checklist_a_ia_nem_e_chamada(): void
    {
        $os = $this->os($this->ativo());
        Http::fake();

        Livewire::test(AtualizarComFolha::class, ['record' => $os->id])->call('lerChecklistComIA')->assertNotified('Escolha o checklist.');
        Livewire::test(AtualizarComFolha::class, ['record' => $os->id])->set('ckTemplateId', $this->tpl()->id)->call('lerChecklistComIA')->assertNotified('Tire ou envie a foto da folha do checklist primeiro.');

        Http::assertNothingSent();
    }

    public function test_um_checklist_de_outro_tenant_nao_pode_ser_lido_nesta_os(): void
    {
        $os = $this->os($this->ativo());
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [CatalogoItem::FEATURE_KEY => true, CkTemplate::FEATURE_KEY => true, 'tabela_ck_execucoes' => true, 'tabela_assets' => true]]);
        $outro = Tenant::create(['name' => 'Outro '.uniqid(), 'slug' => 'ia2-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        app(CatalogoInstalador::class)->instalar($outro);
        app(ChecklistInstalador::class)->instalar($outro, 'gerador');
        $deles = CkTemplate::withoutGlobalScopes()->where('tenant_id', $outro->id)->value('id');
        Http::fake();

        Livewire::test(AtualizarComFolha::class, ['record' => $os->id])->set('ckTemplateId', $deles)->set('ckData.foto', self::FOTO)->call('lerChecklistComIA')->assertNotified('Escolha o checklist.');

        Http::assertNothingSent();
    }
}
