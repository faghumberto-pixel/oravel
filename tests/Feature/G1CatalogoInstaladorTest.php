<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\CatalogoAtributo;
use App\Models\CatalogoInstalacao;
use App\Models\CatalogoItem;
use App\Models\CatalogoPreventiva;
use App\Models\CatalogoRegra;
use App\Models\CatalogoRequisito;
use App\Models\CatalogoSla;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\TenantProvisioner;
use App\Support\SaaSRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * G1 fase 1: schema do catalogo + instalador idempotente do template YAML.
 *
 * SEGURANCA: config/database.php fixa 'pgsql', entao o sqlite do phpunit.xml NAO vale. Estes testes so rodam
 * num banco cujo nome contenha "test" (ex.: DB_DATABASE=oravel_test_g1 php artisan test --filter=G1Catalogo).
 * DatabaseTransactions (rollback por teste); nunca RefreshDatabase.
 */
class G1CatalogoInstaladorTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private Tenant $outro;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco "'.DB::connection()->getDatabaseName().'" nao parece descartavel (nome sem "test").');
        }

        $this->tenant = $this->novoTenant('a');
        $this->outro = $this->novoTenant('b');
    }

    private function novoTenant(string $s, array $features = []): Tenant
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly',
            'is_active' => true, 'features' => $features]);

        return Tenant::create(['name' => "T{$s} ".uniqid(), 'slug' => "g1{$s}-".uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function grupo(Tenant $t, string $slug = 'compressor'): ?AssetCategory
    {
        return AssetCategory::withoutGlobalScopes()->withTrashed()->where('tenant_id', $t->id)->where('slug', $slug)->first();
    }

    private function total(string $classe, Tenant $t): int
    {
        return $classe::withoutGlobalScopes()->withTrashed()->where('tenant_id', $t->id)->count();
    }

    public function test_instala_compressor_completo_com_metadados(): void
    {
        $r = app(CatalogoInstalador::class)->instalar($this->tenant);

        $g = $this->grupo($this->tenant);
        $this->assertNotNull($g);
        $this->assertSame('horimetro', $g->medidor);
        $this->assertSame('media', $g->criticidade_padrao);
        $this->assertFalse($g->exige_operador);
        $this->assertSame('sistema', $g->origem);
        $this->assertSame('compressor', $g->slug_template);
        $this->assertFalse($g->personalizado);
        $this->assertTrue($g->is_active);
        $this->assertSame(6, $r['criados']['grupos'], 'template: compressor + gerador + empilhadeira + plataforma + munk + guindaste');

        $this->assertSame(4, CatalogoAtributo::withoutGlobalScopes()->where('grupo_id', $g->id)->count());
        $this->assertSame(10, CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $g->id)->count());
        $this->assertSame(3, CatalogoPreventiva::withoutGlobalScopes()->where('grupo_id', $g->id)->count());
        $this->assertSame(4, CatalogoSla::withoutGlobalScopes()->where('grupo_id', $g->id)->count());
        $this->assertSame('nr13', CatalogoRegra::withoutGlobalScopes()->where('grupo_id', $g->id)->first()->strategy);
        $this->assertSame(10, CatalogoPreventiva::withoutGlobalScopes()->where('grupo_id', $g->id)->first()->tolerancia_pct);
        $this->assertSame(1, CatalogoInstalacao::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->value('versao_instalada'));
    }

    public function test_itens_dependentes_de_norma_ficam_marcados_para_validar(): void
    {
        app(CatalogoInstalador::class)->instalar($this->tenant);
        $g = $this->grupo($this->tenant);

        $nr13 = CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $g->id)->where('norma', 'NR-13')->get();

        $this->assertCount(4, $nr13);
        $this->assertTrue($nr13->every(fn ($r) => $r->validar_norma));
    }

    public function test_instalacao_e_idempotente(): void
    {
        $i = app(CatalogoInstalador::class);
        $i->instalar($this->tenant);
        $antes = [$this->total(AssetCategory::class, $this->tenant), $this->total(CatalogoItem::class, $this->tenant), $this->total(CatalogoRequisito::class, $this->tenant)];

        $r = $i->instalar($this->tenant);

        $this->assertSame($antes, [$this->total(AssetCategory::class, $this->tenant), $this->total(CatalogoItem::class, $this->tenant), $this->total(CatalogoRequisito::class, $this->tenant)]);
        $this->assertSame([], $r['criados']);
    }

    public function test_nao_sobrescreve_ajuste_do_tenant(): void
    {
        $i = app(CatalogoInstalador::class);
        $i->instalar($this->tenant);
        $g = $this->grupo($this->tenant);
        $g->update(['criticidade_padrao' => 'alta', 'personalizado' => true, 'name' => 'Compressor (nosso)']);
        $req = CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $g->id)->where('slug', 'manometro')->first();
        $req->update(['efeito' => 'ALR', 'personalizado' => true]);
        $item = CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'oleo')->first();
        $item->update(['nome' => 'Oleo (nosso nome)']);

        $i->instalar($this->tenant);

        $this->assertSame('alta', $g->fresh()->criticidade_padrao);
        $this->assertSame('Compressor (nosso)', $g->fresh()->name);
        $this->assertSame('ALR', $req->fresh()->efeito);
        $this->assertSame('Oleo (nosso nome)', $item->fresh()->nome);
    }

    public function test_item_arquivado_pelo_tenant_nao_e_recriado(): void
    {
        $i = app(CatalogoInstalador::class);
        $i->instalar($this->tenant);
        $g = $this->grupo($this->tenant);
        $req = CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $g->id)->where('slug', 'oleo')->first();
        $req->update(['ativo' => false]);
        $req->delete();
        $g->delete(); // grupo arquivado tambem nao volta

        $r = $i->instalar($this->tenant);

        $this->assertSame(0, AssetCategory::query()->where('tenant_id', $this->tenant->id)->where('slug', 'compressor')->count()); // arquivado some das listas
        $this->assertNotNull($this->grupo($this->tenant)); // mas continua la e nao foi recriado
        $this->assertSame(1, CatalogoRequisito::withoutGlobalScopes()->onlyTrashed()->where('slug', 'oleo')->count());
        $this->assertSame([], $r['criados']);
    }

    public function test_categoria_existente_com_mesmo_slug_nao_e_adotada_nem_duplicada(): void
    {
        $propria = AssetCategory::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Compressor', 'slug' => 'compressor']);

        $r = app(CatalogoInstalador::class)->instalar($this->tenant);

        $this->assertSame(['compressor'], $r['conflitos']);
        $this->assertSame(1, AssetCategory::withoutGlobalScopes()->withTrashed()->where('tenant_id', $this->tenant->id)->where('slug', 'compressor')->count());
        $this->assertNull($propria->fresh()->slug_template);
        $this->assertSame(0, CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $propria->id)->count(), 'a categoria propria do tenant nao ganha filhos');
        $this->assertSame(5, AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('origem', 'sistema')->count(), 'os outros 5 grupos instalam normalmente');
    }

    public function test_isolamento_entre_tenants(): void
    {
        app(CatalogoInstalador::class)->instalar($this->tenant);

        $this->assertSame(0, $this->total(AssetCategory::class, $this->outro));
        $this->assertSame(0, $this->total(CatalogoItem::class, $this->outro));

        app(CatalogoInstalador::class)->instalar($this->outro);

        $this->assertSame($this->total(CatalogoItem::class, $this->tenant), $this->total(CatalogoItem::class, $this->outro));
        $this->assertNotSame($this->grupo($this->tenant)->id, $this->grupo($this->outro)->id);
    }

    public function test_template_invalido_nao_grava_nada(): void
    {
        $dir = sys_get_temp_dir().'/g1cat_'.uniqid();
        mkdir($dir);
        copy(CatalogoInstalador::diretorio().'/_vocabulario.yaml', $dir.'/_vocabulario.yaml');
        copy(CatalogoInstalador::diretorio().'/_biblioteca.yaml', $dir.'/_biblioteca.yaml');
        file_put_contents($dir.'/ruim.yaml', "versao: 1\ngrupo: {slug: ruim, nome: Ruim, medidor: horimetro, criticidade_padrao: media}\nrequisitos:\n  - {slug: x, item: nao-existe, efeito: IMP}\n");

        try {
            app(CatalogoInstalador::class)->instalar($this->tenant, $dir);
            $this->fail('Deveria recusar template com item inexistente na biblioteca');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('item inexistente', $e->getMessage());
        } finally {
            array_map('unlink', glob($dir.'/*'));
            rmdir($dir);
        }

        $this->assertSame(0, $this->total(CatalogoItem::class, $this->tenant));
    }

    public function test_chave_do_catalogo_e_modulo_saas_e_nao_vem_ligada(): void
    {
        $this->assertSame('tabela_catalogo_equipamentos', CatalogoItem::saasFeatureKey());
        $this->assertNotNull(SaaSRegistry::forModel(CatalogoItem::class), 'CatalogoItem precisa estar no SaaSRegistry (senao o gate de plano e pulado)');
        $this->assertFalse($this->tenant->plan->hasFeature(CatalogoItem::FEATURE_KEY));
    }

    public function test_provisionamento_so_instala_com_a_chave_ligada(): void
    {
        $desligado = $this->novoTenant('c');
        $ligado = $this->novoTenant('d', [CatalogoItem::FEATURE_KEY => true]);

        foreach ([$desligado, $ligado] as $t) {
            TenantProvisioner::provision($t, ['name' => 'Adm', 'email' => 'adm-'.uniqid().'@oravel.com.br', 'password' => 'x12345678']);
        }

        $this->assertSame(0, $this->total(AssetCategory::class, $desligado));
        $this->assertSame(6, $this->total(AssetCategory::class, $ligado));
    }

    public function test_comando_dry_run_nao_grava(): void
    {
        $this->artisan('catalogo:instalar', ['tenant' => $this->tenant->id, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, $this->total(AssetCategory::class, $this->tenant));

        $this->artisan('catalogo:instalar', ['tenant' => $this->tenant->id])->assertSuccessful();
        $this->assertSame(6, $this->total(AssetCategory::class, $this->tenant));
    }

    public function test_slug_de_requisito_unico_por_grupo_no_banco(): void
    {
        app(CatalogoInstalador::class)->instalar($this->tenant);
        $g = $this->grupo($this->tenant);
        $item = CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();

        $this->expectException(QueryException::class);
        CatalogoRequisito::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'grupo_id' => $g->id, 'item_id' => $item->id, 'slug' => 'manometro', 'efeito' => 'IMP']);
    }
}
