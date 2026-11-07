<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoAtributo;
use App\Models\CatalogoCumprimento;
use App\Models\CatalogoItem;
use App\Models\CatalogoOperador;
use App\Models\CatalogoPreventiva;
use App\Models\CatalogoRegra;
use App\Models\CatalogoRequisito;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Catalogo\Aptidao\AssetFitnessService;
use App\Services\Catalogo\Aptidao\ResultadoAptidao;
use App\Services\Catalogo\CatalogoInstalador;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * G1 fase 4: os grupos do MVP em YAML (compressor, gerador, empilhadeira, plataforma, munk, guindaste) conferidos contra a
 * especificacao, comparativo lado a lado e motor ponta a ponta. Leves/caminhao/reboque ficam na fase 8.
 * SEGURANCA: so roda em banco com "test" no nome.
 */
class G1GruposMvpTest extends TestCase
{
    use DatabaseTransactions;

    private const GRUPOS = ['compressor', 'gerador', 'empilhadeira', 'plataforma-elevatoria', 'munk', 'guindaste'];

    private Tenant $tenant;

    private AssetFitnessService $motor;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }

        $this->motor = app(AssetFitnessService::class);
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [CatalogoItem::FEATURE_KEY => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'g1m-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        app(CatalogoInstalador::class)->instalar($this->tenant);
    }

    private function g(string $slug): AssetCategory
    {
        return AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', $slug)->firstOrFail();
    }

    private function reqs(string $slug)
    {
        return CatalogoRequisito::withoutGlobalScopes()->with(['item' => fn ($q) => $q->withoutGlobalScopes()])->where('grupo_id', $this->g($slug)->id)->get();
    }

    private function prevs(string $slug)
    {
        return CatalogoPreventiva::withoutGlobalScopes()->where('grupo_id', $this->g($slug)->id)->orderBy('intervalo')->get();
    }

    private function ativo(string $grupo, array $extra = []): Asset
    {
        return Asset::factory()->create(array_merge(['tenant_id' => $this->tenant->id, 'asset_category_id' => $this->g($grupo)->id, 'horimetro_atual' => 100, 'odometro_atual' => 0], $extra));
    }

    private function cumprir(Asset $a, CatalogoRequisito $r, string $resultado = 'ok', array $extra = []): CatalogoCumprimento
    {
        return CatalogoCumprimento::create(array_merge(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'requisito_id' => $r->id, 'resultado' => $resultado, 'realizado_em' => now()], $extra));
    }

    // ------------------------------------------------------------ conteudo x especificacao

    public function test_os_seis_grupos_do_mvp_sao_instalados_e_leves_caminhao_reboque_nao(): void
    {
        foreach (self::GRUPOS as $slug) {
            $this->assertNotNull($this->g($slug));
        }

        foreach (['veiculos-leves', 'caminhao', 'reboque'] as $fase8) {
            $this->assertSame(0, AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', $fase8)->count());
        }

        $this->assertSame(6, AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('origem', 'sistema')->count());
    }

    public function test_reinstalar_com_os_seis_grupos_e_idempotente(): void
    {
        $antes = CatalogoRequisito::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count();

        $r = app(CatalogoInstalador::class)->instalar($this->tenant);

        $this->assertSame([], $r['criados']);
        $this->assertSame($antes, CatalogoRequisito::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_gerador_conforme_a_especificacao(): void
    {
        $g = $this->g('gerador');
        $this->assertSame('horimetro', $g->medidor);
        $this->assertSame('media', $g->criticidade_padrao);
        $this->assertFalse($g->exige_operador);
        $this->assertSame(0, CatalogoOperador::withoutGlobalScopes()->where('grupo_id', $g->id)->count());
        $this->assertSame(6, $this->reqs('gerador')->where('efeito', 'IMP')->count());
        $this->assertSame(2, $this->reqs('gerador')->where('efeito', 'ALR')->count());
        $this->assertEqualsCanonicalizing(['oleo', 'arrefecimento', 'bateria-partida', 'disjuntor-painel', 'aterramento', 'vazamentos'], $this->reqs('gerador')->where('efeito', 'IMP')->pluck('item.slug')->all());
        $this->assertSame([250, 500, 1000, 2000], $this->prevs('gerador')->pluck('intervalo')->all());
        $this->assertEqualsCanonicalizing(['kva', 'tensao', 'fases', 'combustivel', 'tipo', 'capacidade-tanque'], CatalogoAtributo::withoutGlobalScopes()->where('grupo_id', $g->id)->pluck('chave')->all());
    }

    public function test_empilhadeira_conforme_a_especificacao(): void
    {
        $g = $this->g('empilhadeira');
        $this->assertTrue($g->exige_operador);
        $this->assertSame(6, $this->reqs('empilhadeira')->where('efeito', 'IMP')->count());
        $this->assertSame(3, $this->reqs('empilhadeira')->where('efeito', 'ALR')->count());
        $this->assertSame([250, 500, 1000, 2000], $this->prevs('empilhadeira')->pluck('intervalo')->all());
        $op = CatalogoOperador::withoutGlobalScopes()->where('grupo_id', $g->id)->get();
        $this->assertCount(1, $op);
        $this->assertSame('NR-11', $op[0]->norma);
    }

    public function test_plataforma_conforme_a_especificacao(): void
    {
        $this->assertSame('alta', $this->g('plataforma-elevatoria')->criticidade_padrao);
        $this->assertSame(5, $this->reqs('plataforma-elevatoria')->where('efeito', 'IMP')->count());
        $this->assertSame(2, $this->reqs('plataforma-elevatoria')->where('efeito', 'ALR')->count());
        $this->assertSame([250, 500, 1000], $this->prevs('plataforma-elevatoria')->pluck('intervalo')->all());
        $this->assertSame(1, $this->reqs('plataforma-elevatoria')->where('efeito', 'INSP')->count());
    }

    public function test_munk_usa_os_dois_medidores(): void
    {
        $this->assertSame('ambos', $this->g('munk')->medidor);
        $this->assertEqualsCanonicalizing(['km', 'horimetro'], $this->prevs('munk')->pluck('medidor')->all());
        $this->assertSame(7, $this->reqs('munk')->where('efeito', 'IMP')->count());
        $this->assertEqualsCanonicalizing(['crlv', 'seguro', 'art', 'tabela-carga-legivel'], $this->reqs('munk')->where('efeito', 'DOC')->pluck('item.slug')->all());
        // intervalos sem especificacao: marcados para validar com o manual do fabricante
        $this->assertTrue($this->prevs('munk')->every(fn ($p) => ! empty($p->parametros['validar_manual'])));
    }

    public function test_guindaste_e_critico_e_tem_regra_de_plano_de_icamento(): void
    {
        $this->assertSame('critica', $this->g('guindaste')->criticidade_padrao);
        $this->assertSame(7, $this->reqs('guindaste')->where('efeito', 'IMP')->count());
        $regra = CatalogoRegra::withoutGlobalScopes()->where('grupo_id', $this->g('guindaste')->id)->firstOrFail();
        $this->assertSame('bloqueia_alocacao', $regra->efeito);
        $this->assertSame(['sem_cumprimento' => 'plano-icamento'], $regra->condicao);
        $this->assertNotNull($this->reqs('guindaste')->firstWhere('slug', 'plano-icamento'));
    }

    public function test_todo_item_dependente_de_norma_esta_marcado_para_validar(): void
    {
        foreach (self::GRUPOS as $slug) {
            foreach ($this->reqs($slug)->whereNotNull('norma') as $r) {
                $this->assertTrue($r->validar_norma, "{$slug}/{$r->slug} cita norma ({$r->norma}) e precisa estar marcado para validar");
            }
            foreach (CatalogoOperador::withoutGlobalScopes()->where('grupo_id', $this->g($slug)->id)->get() as $o) {
                $this->assertTrue($o->validar_norma, "{$slug}/{$o->slug}");
            }
        }
        foreach (CatalogoRegra::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->get() as $r) {
            $this->assertTrue($r->validar_norma, "regra {$r->slug}");
        }
    }

    public function test_comparativo_lado_a_lado_guindaste_gerador_compressor_sao_perfis_distintos(): void
    {
        $perfil = fn (string $s) => [
            'attrs' => CatalogoAtributo::withoutGlobalScopes()->where('grupo_id', $this->g($s)->id)->pluck('chave')->sort()->values()->all(),
            'req' => $this->reqs($s)->pluck('slug')->sort()->values()->all(),
            'oper' => $this->g($s)->exige_operador,
            'crit' => $this->g($s)->criticidade_padrao,
        ];
        [$gui, $ger, $comp] = [$perfil('guindaste'), $perfil('gerador'), $perfil('compressor')];

        $this->assertContains('tabela-carga', $gui['attrs']);
        $this->assertNotContains('tabela-carga', $ger['attrs']);
        $this->assertContains('kva', $ger['attrs']);
        $this->assertNotContains('kva', $gui['attrs']);
        $this->assertContains('pressao-maxima', $comp['attrs']);
        $this->assertSame([], array_values(array_diff(array_intersect($ger['attrs'], $comp['attrs']), ['tipo'])), 'gerador e compressor so compartilham o rotulo generico "tipo"');
        $this->assertTrue($gui['oper']);
        $this->assertFalse($ger['oper']);
        $this->assertFalse($comp['oper']);
        $this->assertSame(['critica', 'media', 'media'], [$gui['crit'], $ger['crit'], $comp['crit']]);
        $this->assertContains('plano-icamento', $gui['req']);
        $this->assertNotContains('plano-icamento', $ger['req']);
        $this->assertContains('aterramento', $ger['req']);
        $this->assertNotContains('aterramento', $gui['req']);
        $this->assertContains('inspecao-vaso-pressao', $comp['req']);
    }

    public function test_item_da_biblioteca_e_reutilizado_entre_grupos_sem_duplicar(): void
    {
        $this->assertSame(1, CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'vazamentos')->count());
        $item = CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'vazamentos')->firstOrFail();

        $this->assertGreaterThanOrEqual(5, CatalogoRequisito::withoutGlobalScopes()->where('item_id', $item->id)->distinct('grupo_id')->count('grupo_id'));
        // mesmo item, efeito proprio por grupo: ALR no compressor, IMP nos demais
        $efeitos = CatalogoRequisito::withoutGlobalScopes()->where('item_id', $item->id)->pluck('efeito', 'grupo_id');
        $this->assertSame('ALR', $efeitos[$this->g('compressor')->id]);
        $this->assertSame('IMP', $efeitos[$this->g('gerador')->id]);
    }

    // ------------------------------------------------------------ motor ponta a ponta

    public function test_gerador_sem_operador_exigido_e_aptidao_por_horas(): void
    {
        $a = $this->ativo('gerador', ['horimetro_atual' => 1000]);
        foreach ($this->prevs('gerador') as $p) {
            CatalogoCumprimento::create(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'preventiva_id' => $p->id, 'resultado' => 'ok', 'realizado_em' => now(), 'medidor_leitura' => 1000]);
        }

        $this->assertSame(ResultadoAptidao::APTO, $this->motor->calcular($a->fresh())->status);

        $this->cumprir($a, $this->reqs('gerador')->firstWhere('slug', 'aterramento'), 'reprovado');
        $r = $this->motor->calcular($a->fresh());
        $this->assertSame(ResultadoAptidao::INAPTO, $r->status);
        $this->assertSame(['aterramento'], $r->osAGerar);
    }

    public function test_guindaste_bloqueia_alocacao_ate_ter_plano_de_icamento(): void
    {
        $a = $this->ativo('guindaste');
        $this->assertTrue($this->motor->calcular($a)->bloqueiaAlocacao);

        $plano = $this->reqs('guindaste')->firstWhere('slug', 'plano-icamento');
        $this->cumprir($a, $plano, 'ok', ['valido_ate' => now()->addDays(3)->toDateString()]);
        $this->assertFalse($this->motor->calcular($a)->bloqueiaAlocacao);

        // plano vencido volta a bloquear
        CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->update(['valido_ate' => now()->subDay()->toDateString()]);
        $this->assertTrue($this->motor->calcular($a)->bloqueiaAlocacao);
    }

    public function test_munk_avalia_km_do_chassi_e_horas_do_guindaste_separadamente(): void
    {
        $a = $this->ativo('munk', ['odometro_atual' => 30000, 'horimetro_atual' => 100]);
        $km = $this->prevs('munk')->firstWhere('medidor', 'km');
        $h = $this->prevs('munk')->firstWhere('medidor', 'horimetro');
        CatalogoCumprimento::create(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'preventiva_id' => $km->id, 'resultado' => 'ok', 'realizado_em' => now(), 'medidor_leitura' => 15000]);
        CatalogoCumprimento::create(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'preventiva_id' => $h->id, 'resultado' => 'ok', 'realizado_em' => now(), 'medidor_leitura' => 50]);

        $codigos = array_column($this->motor->calcular($a->fresh())->motivos, 'codigo');

        $this->assertContains('prev:'.$km->slug, $codigos, 'chassi: 15.000 km rodados desde a revisao (intervalo 10.000)');
        $this->assertNotContains('prev:'.$h->slug, $codigos, 'guindaste: 50 h usadas, dentro do intervalo');
    }

    public function test_empilhadeira_reprovar_freio_impede_e_alerta_nao(): void
    {
        $a = $this->ativo('empilhadeira');
        $this->cumprir($a, $this->reqs('empilhadeira')->firstWhere('slug', 'luzes'), 'reprovado');
        $this->assertSame(ResultadoAptidao::RESTRICAO, $this->motor->calcular($a)->status);

        $this->cumprir($a, $this->reqs('empilhadeira')->firstWhere('slug', 'freios'), 'reprovado');
        $this->assertSame(ResultadoAptidao::INAPTO, $this->motor->calcular($a)->status);
    }
}
