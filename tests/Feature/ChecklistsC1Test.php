<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\CatalogoRequisito;
use App\Models\CkAnswer;
use App\Models\CkExecution;
use App\Models\CkItem;
use App\Models\CkSection;
use App\Models\CkTemplate;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\CkExecutionPolicy;
use App\Policies\CkTemplatePolicy;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Checklists\ChecklistInstalador;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Checklists por grupo de equipamento, C1: schema, models, instalador dos 8 templates padrao (idempotente, nunca sobrescreve
 * o que o tenant editou), isolamento entre tenants e snapshot das execucoes.
 * SEGURANCA: so roda em banco com "test" no nome.
 */
class ChecklistsC1Test extends TestCase
{
    use DatabaseTransactions;

    private const GRUPOS = ['gerador', 'compressor', 'plataforma-elevatoria', 'guindaste', 'munk', 'veiculos-leves', 'caminhao', 'empilhadeira'];

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }
    }

    private function tenant(array $features = [CatalogoItem::FEATURE_KEY => true, CkTemplate::FEATURE_KEY => true]): Tenant
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features + ['tabela_assets' => true]]);

        return Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'ck-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function admin(Tenant $t): User
    {
        $u = User::create(['name' => 'U', 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return $u;
    }

    /** Tenant com o catalogo G1 instalado (para os templates ja nascerem ligados aos grupos). */
    private function tenantComCatalogo(): Tenant
    {
        $t = $this->tenant();
        app(CatalogoInstalador::class)->instalar($t);

        return $t;
    }

    private function inst(): ChecklistInstalador
    {
        return app(ChecklistInstalador::class);
    }

    private function templates(Tenant $t)
    {
        return CkTemplate::withoutGlobalScopes()->where('tenant_id', $t->id);
    }

    // ------------------------------------------------------------ conteudo dos YAML

    public function test_os_8_yaml_padrao_sao_validos_e_cobrem_os_8_grupos(): void
    {
        $defs = $this->inst()->carregar();

        $this->assertCount(8, $defs);
        $this->assertEqualsCanonicalizing(self::GRUPOS, collect($defs)->pluck('template.grupo')->all());

        foreach ($defs as $slug => $d) {
            $arquivo = ChecklistInstalador::diretorio().'/'.$d['_arquivo'];
            $this->assertStringContainsString('REVISAR COM O RESPONSAVEL TECNICO', file_get_contents($arquivo), $slug.': falta o aviso de revisao tecnica');
            $this->assertNotEmpty($d['template']['referencia_manual'], $slug.': falta a referencia do conjunto');
            $this->assertContains($d['template']['tipo'], ChecklistInstalador::TIPOS, $slug);
            $this->assertContains($d['template']['periodicidade'], ChecklistInstalador::PERIODICIDADES, $slug);

            $itens = collect($d['secoes'])->flatMap(fn ($s) => $s['itens']);
            $this->assertGreaterThanOrEqual(15, $itens->count(), $slug.': menos de 15 itens');
            $this->assertLessThanOrEqual(30, $itens->count(), $slug.': mais de 30 itens');
            $this->assertGreaterThan(0, $itens->where('critico', true)->count(), $slug.': nenhum item critico');
            $this->assertCount($itens->count(), $itens->pluck('slug')->unique(), $slug.': slug de item repetido');
            $this->assertCount(count($d['secoes']), collect($d['secoes'])->pluck('slug')->unique(), $slug.': slug de secao repetido');

            foreach ($itens as $i) {
                $this->assertNotEmpty($i['descricao'], $slug);
                $this->assertContains($i['tipo_resposta'] ?? 'conforme_nao_conforme_na', ChecklistInstalador::TIPOS_RESPOSTA, $slug.'/'.$i['slug']);

                if (($i['tipo_resposta'] ?? null) === 'numerico') {
                    $this->assertNotEmpty($i['unidade'] ?? null, $slug.'/'.$i['slug'].': numerico sem unidade');
                }
            }
        }
    }

    public function test_requisitos_citados_nos_yaml_existem_no_catalogo_do_g1(): void
    {
        $g1 = Yaml::parseFile(CatalogoInstalador::diretorio().'/gerador.yaml');
        $this->assertNotEmpty($g1);

        foreach ($this->inst()->carregar() as $slug => $d) {
            $cat = Yaml::parseFile(CatalogoInstalador::diretorio().'/'.($d['template']['grupo'] === 'plataforma-elevatoria' ? 'plataforma-elevatoria' : $d['template']['grupo']).'.yaml');
            $slugsG1 = collect($cat['requisitos'] ?? [])->pluck('slug')->all();

            foreach (collect($d['secoes'])->flatMap(fn ($s) => $s['itens']) as $i) {
                if (! empty($i['requisito'])) {
                    $this->assertContains($i['requisito'], $slugsG1, "{$slug}/{$i['slug']}: requisito '{$i['requisito']}' nao existe no grupo {$d['template']['grupo']} do G1");
                }
            }
        }
    }

    // ------------------------------------------------------------ instalador

    public function test_instala_os_8_templates_ligados_aos_grupos_e_aos_requisitos_do_g1(): void
    {
        $t = $this->tenantComCatalogo();

        $r = $this->inst()->instalar($t);

        $this->assertSame(8, $r['criados']['templates']);
        $this->assertSame([], $r['avisos']);
        $this->assertSame(8, $this->templates($t)->whereNotNull('grupo_id')->count());
        $this->assertSame(8, $this->templates($t)->where('origem', 'padrao')->where('personalizado', false)->where('ativo', true)->count());

        $gerador = $this->templates($t)->where('slug_template', 'gerador-pre-uso')->firstOrFail();
        $grupo = AssetCategory::withoutGlobalScopes()->where('tenant_id', $t->id)->where('slug_template', 'gerador')->firstOrFail();
        $this->assertSame($grupo->id, $gerador->grupo_id);

        $oleo = CkItem::withoutGlobalScopes()->whereIn('section_id', CkSection::withoutGlobalScopes()->where('template_id', $gerador->id)->pluck('id'))->where('slug_template', 'nivel-oleo')->firstOrFail();
        $this->assertTrue($oleo->critico);
        $this->assertTrue($oleo->exige_foto_se_nao_conforme, 'critico exige foto quando nao conforme por padrao');
        $this->assertNotNull($oleo->catalogo_requisito_id);
        $this->assertSame($grupo->id, CatalogoRequisito::withoutGlobalScopes()->find($oleo->catalogo_requisito_id)->grupo_id, 'o requisito ligado e do mesmo grupo');
        $this->assertTrue($oleo->validar_norma, 'item com referencia entra para validar com o responsavel tecnico');
    }

    public function test_instalar_de_novo_e_idempotente(): void
    {
        $t = $this->tenantComCatalogo();
        $this->inst()->instalar($t);
        $antes = [$this->templates($t)->count(), CkSection::withoutGlobalScopes()->where('tenant_id', $t->id)->count(), CkItem::withoutGlobalScopes()->where('tenant_id', $t->id)->count()];

        $r = $this->inst()->instalar($t);

        $this->assertSame([], $r['criados']);
        $this->assertSame(8, $r['ignorados']['templates']);
        $this->assertSame($antes, [$this->templates($t)->count(), CkSection::withoutGlobalScopes()->where('tenant_id', $t->id)->count(), CkItem::withoutGlobalScopes()->where('tenant_id', $t->id)->count()]);
    }

    public function test_o_que_o_tenant_editou_nao_e_sobrescrito_nem_recriado(): void
    {
        $t = $this->tenantComCatalogo();
        $this->inst()->instalar($t);

        $tpl = $this->templates($t)->where('slug_template', 'gerador-pre-uso')->firstOrFail();
        $tpl->update(['nome' => 'Gerador - roteiro da minha empresa', 'personalizado' => true, 'origem' => CkTemplate::ORIGEM_CUSTOMIZADO]);
        $secao = CkSection::withoutGlobalScopes()->where('template_id', $tpl->id)->firstOrFail();
        $secao->update(['titulo' => 'Minha seção']);
        $item = CkItem::withoutGlobalScopes()->where('section_id', $secao->id)->firstOrFail();
        $item->update(['descricao' => 'Texto que a empresa reescreveu', 'personalizado' => true]);
        $apagado = CkItem::withoutGlobalScopes()->where('section_id', $secao->id)->where('id', '!=', $item->id)->firstOrFail();
        $apagado->delete();

        $this->inst()->instalar($t);

        $this->assertSame('Gerador - roteiro da minha empresa', $tpl->fresh()->nome);
        $this->assertSame('Minha seção', $secao->fresh()->titulo);
        $this->assertSame('Texto que a empresa reescreveu', $item->fresh()->descricao);
        $this->assertTrue(CkItem::withoutGlobalScopes()->withTrashed()->find($apagado->id)->trashed(), 'item que a empresa excluiu nao volta');
        $this->assertSame(1, $this->templates($t)->where('slug_template', 'gerador-pre-uso')->count(), 'sem duplicar');
    }

    public function test_template_arquivado_pelo_tenant_nao_e_recriado(): void
    {
        $t = $this->tenantComCatalogo();
        $this->inst()->instalar($t);
        $this->templates($t)->where('slug_template', 'munk-pre-uso')->firstOrFail()->delete();

        $r = $this->inst()->instalar($t);

        $this->assertSame([], $r['criados']);
        $this->assertSame(0, $this->templates($t)->whereNull('deleted_at')->where('slug_template', 'munk-pre-uso')->count(), 'continua arquivado (fora da lista ativa)');
        $this->assertSame(1, CkTemplate::withoutGlobalScopes()->withTrashed()->where('tenant_id', $t->id)->where('slug_template', 'munk-pre-uso')->count());
    }

    public function test_filtro_por_grupo_instala_so_aquele_checklist(): void
    {
        $t = $this->tenantComCatalogo();

        $r = $this->inst()->instalar($t, 'empilhadeira');

        $this->assertSame(1, $r['criados']['templates']);
        $this->assertSame(['empilhadeira-pre-uso'], $this->templates($t)->pluck('slug_template')->all());
    }

    public function test_sem_o_grupo_do_catalogo_o_template_entra_sem_grupo_e_avisa(): void
    {
        $t = $this->tenant(); // catalogo NAO instalado

        $r = $this->inst()->instalar($t, 'gerador');

        $this->assertSame(1, $r['criados']['templates']);
        $this->assertNotEmpty($r['avisos']);
        $tpl = $this->templates($t)->firstOrFail();
        $this->assertNull($tpl->grupo_id);
        $this->assertSame(0, CkItem::withoutGlobalScopes()->where('tenant_id', $t->id)->whereNotNull('catalogo_requisito_id')->count());
    }

    public function test_comando_instala_por_tenant_e_por_grupo(): void
    {
        $t = $this->tenantComCatalogo();

        $this->artisan('checklists:seed', ['--tenant' => $t->id, '--group' => 'gerador'])->assertSuccessful();
        $this->assertSame(1, $this->templates($t)->count());

        $this->artisan('checklists:seed', ['--tenant' => $t->id])->assertSuccessful();
        $this->assertSame(8, $this->templates($t)->count());

        $this->artisan('checklists:seed', ['--tenant' => '00000000-0000-0000-0000-000000000000'])->assertFailed();
    }

    public function test_provisionamento_so_instala_com_a_chave_dos_checklists_ligada(): void
    {
        $desligado = $this->tenant([CatalogoItem::FEATURE_KEY => true]);
        $ligado = $this->tenant();

        foreach ([$desligado, $ligado] as $t) {
            TenantProvisioner::provision($t, ['name' => 'Adm', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => 'x12345678']);
        }

        $this->assertSame(0, $this->templates($desligado)->count());
        $this->assertSame(8, $this->templates($ligado)->count());
        $this->assertSame(8, $this->templates($ligado)->whereNotNull('grupo_id')->count(), 'catalogo instalado antes: ja nasce ligado aos grupos');
    }

    // ------------------------------------------------------------ isolamento e snapshot

    public function test_tenant_a_nao_ve_nem_edita_templates_do_tenant_b(): void
    {
        $a = $this->tenantComCatalogo();
        $b = $this->tenantComCatalogo();
        $this->inst()->instalar($a);
        $this->inst()->instalar($b, 'gerador');

        $this->actingAs($this->admin($b));

        $this->assertSame(1, CkTemplate::count(), 'B ve so o dele');
        $this->assertSame(0, CkTemplate::where('tenant_id', $a->id)->count());
        $this->assertSame(0, CkSection::where('tenant_id', $a->id)->count());
        $this->assertSame(0, CkItem::where('tenant_id', $a->id)->count());

        $idDeA = $this->templates($a)->where('slug_template', 'gerador-pre-uso')->value('id');
        $this->assertNull(CkTemplate::find($idDeA), 'B nao encontra o template de A pelo id');
        $this->assertSame(0, CkTemplate::whereKey($idDeA)->update(['nome' => 'invadido']), 'B nao consegue editar o template de A');
        $this->assertNotSame('invadido', CkTemplate::withoutGlobalScopes()->find($idDeA)->nome);
    }

    public function test_editar_o_template_nao_muda_execucoes_antigas(): void
    {
        $t = $this->tenantComCatalogo();
        $this->inst()->instalar($t, 'gerador');
        $this->actingAs($u = $this->admin($t));

        $tpl = CkTemplate::firstOrFail();
        $item = CkItem::where('slug_template', 'nivel-oleo')->firstOrFail();
        $ativo = Asset::factory()->create(['tenant_id' => $t->id, 'status' => Asset::STATUS_DISPONIVEL]);

        $exec = CkExecution::create([
            'asset_id' => $ativo->id, 'template_id' => $tpl->id, 'template_nome' => $tpl->nome, 'versao_template' => $tpl->versao,
            'executor_id' => $u->id, 'iniciado_em' => now(), 'status' => CkExecution::STATUS_EM_ANDAMENTO,
        ]);
        $resp = CkAnswer::create([
            'execution_id' => $exec->id, 'item_id' => $item->id, 'secao_titulo' => $item->secao->titulo, 'item_descricao' => $item->descricao,
            'tipo_resposta' => $item->tipo_resposta, 'critico' => $item->critico, 'referencia_manual' => $item->referencia_manual, 'resposta' => 'conforme',
        ]);
        $nomeAntes = $exec->template_nome;
        $textoAntes = $resp->item_descricao;

        $item->update(['descricao' => 'Texto novo do item', 'critico' => false]);
        $tpl->update(['nome' => 'Nome novo do checklist', 'versao' => $tpl->versao + 1]);

        $exec = $exec->fresh();
        $resp = $resp->fresh();
        $this->assertSame($nomeAntes, $exec->template_nome);
        $this->assertSame(1, $exec->versao_template);
        $this->assertSame($textoAntes, $resp->item_descricao);
        $this->assertTrue($resp->critico, 'a criticidade da epoca fica no snapshot');
        $this->assertSame('Nome novo do checklist', $exec->template->nome, 'o template em si continua editavel');
    }

    public function test_apagar_o_item_do_template_nao_apaga_a_resposta_antiga(): void
    {
        $t = $this->tenantComCatalogo();
        $this->inst()->instalar($t, 'gerador');
        $this->actingAs($u = $this->admin($t));

        $tpl = CkTemplate::firstOrFail();
        $item = CkItem::firstOrFail();
        $ativo = Asset::factory()->create(['tenant_id' => $t->id, 'status' => Asset::STATUS_DISPONIVEL]);
        $exec = CkExecution::create(['asset_id' => $ativo->id, 'template_id' => $tpl->id, 'template_nome' => $tpl->nome, 'versao_template' => 1, 'executor_id' => $u->id, 'iniciado_em' => now()]);
        $resp = CkAnswer::create(['execution_id' => $exec->id, 'item_id' => $item->id, 'item_descricao' => $item->descricao, 'tipo_resposta' => $item->tipo_resposta, 'resposta' => 'conforme']);

        $item->forceDelete();

        $this->assertSame($item->descricao, $resp->fresh()->item_descricao);
        $this->assertNull($resp->fresh()->item_id);
    }

    public function test_modulos_saas_e_policies_dos_checklists_estao_registrados(): void
    {
        $this->assertSame('tabela_ck_templates', CkTemplate::saasFeatureKey());
        $this->assertSame('tabela_ck_execucoes', CkExecution::saasFeatureKey());
        $this->assertTrue(class_exists(CkTemplatePolicy::class));
        $this->assertTrue(class_exists(CkExecutionPolicy::class));
    }
}
