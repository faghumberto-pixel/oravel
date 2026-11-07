<?php

namespace Tests\Feature;

use App\Filament\Resources\CatalogoGrupoResource;
use App\Filament\Resources\CatalogoGrupoResource\Pages\EditCatalogoGrupo;
use App\Filament\Resources\CatalogoGrupoResource\Pages\ListCatalogoGrupos;
use App\Filament\Resources\CatalogoItemResource;
use App\Filament\Resources\CatalogoItemResource\Pages\EditCatalogoItem;
use App\Filament\Resources\CatalogoItemResource\Pages\ListCatalogoItens;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\CatalogoPreventiva;
use App\Models\CatalogoRequisito;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoGestao;
use App\Services\Catalogo\CatalogoInstalador;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * G1 fase 2: acesso, clonar, arquivar/excluir, edicao sensivel com justificativa e log.
 * SEGURANCA: so roda em banco com "test" no nome (config/database.php fixa pgsql; o sqlite do phpunit.xml nao vale).
 */
class G1CatalogoGestaoTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    private AssetCategory $grupo;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }

        $this->tenant = $this->novoTenant(true);
        $this->admin = $this->novoUsuario($this->tenant, admin: true);
        app(CatalogoInstalador::class)->instalar($this->tenant);
        $this->grupo = AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'compressor')->firstOrFail();

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function novoTenant(bool $comChave): Tenant
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly',
            'is_active' => true, 'features' => $comChave ? [CatalogoItem::FEATURE_KEY => true] : []]);

        return Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'g1g-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function novoUsuario(Tenant $t, bool $admin = false, array $permissoes = []): User
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

    private function ativo(AssetCategory $g): Asset
    {
        return Asset::factory()->create(['tenant_id' => $g->tenant_id, 'asset_category_id' => $g->id]);
    }

    private function req(string $slug): CatalogoRequisito
    {
        return CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $this->grupo->id)->where('slug', $slug)->firstOrFail();
    }

    // ---------------------------------------------------------------- acesso

    public function test_chave_desligada_nega_ate_admin_do_tenant(): void
    {
        $semChave = $this->novoTenant(false);
        $this->actingAs($this->novoUsuario($semChave, admin: true));

        $this->assertFalse(CatalogoGrupoResource::canViewAny());
        $this->assertFalse(CatalogoGrupoResource::canCreate());
        $this->assertFalse(CatalogoItemResource::canViewAny());
        $this->assertFalse(CatalogoGrupoResource::shouldRegisterNavigation());
    }

    public function test_admin_com_chave_ligada_gerencia(): void
    {
        $this->assertTrue(CatalogoGrupoResource::canViewAny());
        $this->assertTrue(CatalogoGrupoResource::canCreate());
        $this->assertTrue(CatalogoGrupoResource::canEdit($this->grupo));
    }

    public function test_perfil_que_so_le_nao_gerencia(): void
    {
        $leitor = $this->novoUsuario($this->tenant, permissoes: ['ler_catalogo_equipamentos']);
        $this->actingAs($leitor);

        $this->assertTrue(CatalogoGrupoResource::canViewAny());
        $this->assertFalse(CatalogoGrupoResource::canCreate());
        $this->assertFalse(CatalogoGrupoResource::canEdit($this->grupo));
    }

    public function test_perfil_sem_permissao_nao_ve(): void
    {
        $this->actingAs($this->novoUsuario($this->tenant));

        $this->assertFalse(CatalogoGrupoResource::canViewAny());
    }

    public function test_perfil_com_editar_gerencia(): void
    {
        $this->actingAs($this->novoUsuario($this->tenant, permissoes: ['ler_catalogo_equipamentos', 'editar_catalogo_equipamentos']));

        $this->assertTrue(CatalogoGrupoResource::canEdit($this->grupo));
    }

    // ---------------------------------------------------------------- clonar

    public function test_clonar_grupo_copia_perfil_e_filhos_como_tenant_personalizado(): void
    {
        $novo = app(CatalogoGestao::class)->clonarGrupo($this->grupo, 'Compressor Parafuso');

        $this->assertSame('compressor-parafuso', $novo->slug);
        $this->assertSame('tenant', $novo->origem);
        $this->assertNull($novo->slug_template);
        $this->assertTrue($novo->personalizado);
        $this->assertSame(10, CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $novo->id)->count());
        $this->assertSame(3, CatalogoPreventiva::withoutGlobalScopes()->where('grupo_id', $novo->id)->count());
        $this->assertSame(0, CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $novo->id)->where('origem', 'sistema')->count());
        // original intacto
        $this->assertSame(10, CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $this->grupo->id)->count());
        $this->assertSame('sistema', $this->grupo->fresh()->origem);
        $this->assertNotNull(Activity::query()->where('description', 'Catalogo: grupo clonado')->first());
    }

    public function test_clonar_duas_vezes_gera_slugs_distintos(): void
    {
        $a = app(CatalogoGestao::class)->clonarGrupo($this->grupo, 'Copia');
        $b = app(CatalogoGestao::class)->clonarGrupo($this->grupo, 'Copia');

        $this->assertNotSame($a->slug, $b->slug);
    }

    // ---------------------------------------------------------------- arquivar / excluir

    public function test_arquivar_grupo_com_ativos_exige_migracao_e_migra(): void
    {
        $destino = app(CatalogoGestao::class)->clonarGrupo($this->grupo, 'Destino');
        $ativo = $this->ativo($this->grupo);
        $g = app(CatalogoGestao::class);

        try {
            $g->arquivarGrupo($this->grupo, null, 'sem destino');
            $this->fail('Deveria exigir grupo de destino');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('migrar_para', $e->errors());
        }
        $this->assertFalse($this->grupo->fresh()->trashed());

        $g->arquivarGrupo($this->grupo, $destino->id, 'Descontinuado pelo fabricante');

        $this->assertTrue(AssetCategory::withoutGlobalScopes()->withTrashed()->find($this->grupo->id)->trashed());
        $this->assertFalse((bool) AssetCategory::withoutGlobalScopes()->withTrashed()->find($this->grupo->id)->is_active);
        $this->assertSame($destino->id, Asset::withoutGlobalScopes()->find($ativo->id)->asset_category_id);
        $log = Activity::query()->where('description', 'Catalogo: grupo arquivado')->first();
        $this->assertSame('Descontinuado pelo fabricante', $log->properties['justificativa']);
        $this->assertSame(1, $log->properties['ativos_migrados']);
    }

    public function test_arquivar_nao_aceita_destino_arquivado_ou_de_outro_tenant(): void
    {
        $outro = $this->novoTenant(true);
        app(CatalogoInstalador::class)->instalar($outro);
        $alheio = AssetCategory::withoutGlobalScopes()->where('tenant_id', $outro->id)->firstOrFail();
        $this->ativo($this->grupo);

        $this->expectException(ValidationException::class);
        app(CatalogoGestao::class)->arquivarGrupo($this->grupo, $alheio->id, 'tentando migrar para outro tenant');
    }

    public function test_arquivar_exige_justificativa(): void
    {
        $this->expectException(ValidationException::class);
        app(CatalogoGestao::class)->arquivarGrupo($this->grupo, null, '');
    }

    public function test_purgar_grupo_so_se_nunca_usado(): void
    {
        $g = app(CatalogoGestao::class);
        $usado = $g->clonarGrupo($this->grupo, 'Usado');
        $this->ativo($usado);
        $livre = $g->clonarGrupo($this->grupo, 'Livre');

        $this->assertFalse($g->purgarGrupo($usado));
        $this->assertNotNull(AssetCategory::withoutGlobalScopes()->find($usado->id));

        $this->assertTrue($g->purgarGrupo($livre));
        $this->assertNull(AssetCategory::withoutGlobalScopes()->withTrashed()->find($livre->id));
        $this->assertSame(0, CatalogoRequisito::withoutGlobalScopes()->withTrashed()->where('grupo_id', $livre->id)->count());
    }

    public function test_item_da_biblioteca_usado_e_arquivado_e_nao_usado_e_excluido(): void
    {
        $g = app(CatalogoGestao::class);
        $usado = CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'extintor')->firstOrFail();
        // extintor nao e usado pelo compressor: vira usado ao referenciar
        CatalogoRequisito::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'grupo_id' => $this->grupo->id, 'item_id' => $usado->id, 'slug' => 'extintor', 'efeito' => 'ALR']);
        $livre = CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'cinto')->firstOrFail();

        $this->assertSame('arquivado', $g->removerItem($usado));
        $this->assertTrue(CatalogoItem::withoutGlobalScopes()->withTrashed()->find($usado->id)->trashed());
        $this->assertFalse((bool) CatalogoItem::withoutGlobalScopes()->withTrashed()->find($usado->id)->ativo);

        $this->assertSame('excluido', $g->removerItem($livre));
        $this->assertNull(CatalogoItem::withoutGlobalScopes()->withTrashed()->find($livre->id));
    }

    public function test_item_impeditivo_em_uso_exige_justificativa_para_arquivar(): void
    {
        $oleo = CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'oleo')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(CatalogoGestao::class)->removerItem($oleo, null);
    }

    // ---------------------------------------------------------------- edicao sensivel

    public function test_detecta_mudancas_sensiveis(): void
    {
        $g = app(CatalogoGestao::class);
        $estado = [
            'requisitos' => collect(CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $this->grupo->id)->get())
                ->mapWithKeys(fn ($r) => ['record-'.$r->id => $r->only(['efeito', 'ativo'])])->all(),
            'preventivas' => collect(CatalogoPreventiva::withoutGlobalScopes()->where('grupo_id', $this->grupo->id)->get())
                ->mapWithKeys(fn ($r) => ['record-'.$r->id => $r->only(['intervalo', 'ativo'])])->all(),
        ];

        $this->assertSame([], $g->mudancasSensiveis($this->grupo, $estado), 'estado igual ao banco nao e sensivel');

        // IMP -> ALR
        $e = $estado;
        $e['requisitos']['record-'.$this->req('manometro')->id]['efeito'] = 'ALR';
        $this->assertSame('efeito_rebaixado', $g->mudancasSensiveis($this->grupo, $e)[0]['tipo']);

        // ALR -> IMP (endurecer) nao e sensivel
        $e = $estado;
        $e['requisitos']['record-'.$this->req('vazamentos-de-ar')->id]['efeito'] = 'IMP';
        $this->assertSame([], $g->mudancasSensiveis($this->grupo, $e));

        // remover inspecao obrigatoria
        $e = $estado;
        unset($e['requisitos']['record-'.$this->req('inspecao-vaso-pressao')->id]);
        $this->assertSame('requisito_removido', $g->mudancasSensiveis($this->grupo, $e)[0]['tipo']);

        // desativar item impeditivo
        $e = $estado;
        $e['requisitos']['record-'.$this->req('oleo')->id]['ativo'] = false;
        $this->assertSame('requisito_desativado', $g->mudancasSensiveis($this->grupo, $e)[0]['tipo']);

        // ampliar intervalo (e reduzir nao e sensivel)
        $p = CatalogoPreventiva::withoutGlobalScopes()->where('grupo_id', $this->grupo->id)->where('slug', 'revisao-500h')->firstOrFail();
        $e = $estado;
        $e['preventivas']['record-'.$p->id]['intervalo'] = 600;
        $this->assertSame('intervalo_ampliado', $g->mudancasSensiveis($this->grupo, $e)[0]['tipo']);
        $e['preventivas']['record-'.$p->id]['intervalo'] = 400;
        $this->assertSame([], $g->mudancasSensiveis($this->grupo, $e));
    }

    public function test_tela_bloqueia_edicao_sensivel_sem_justificativa_e_grava_log_com_justificativa(): void
    {
        $req = $this->req('manometro');
        $t = Livewire::test(EditCatalogoGrupo::class, ['record' => $this->grupo->getKey()]);
        $requisitos = $t->get('data.requisitos');
        $requisitos['record-'.$req->id]['efeito'] = 'ALR';

        $t->set('data.requisitos', $requisitos)->call('save')->assertHasErrors(['data.justificativa']);
        $this->assertSame('IMP', $req->fresh()->efeito, 'nao pode ter salvo sem justificativa');

        $t->set('data.justificativa', 'Fabricante autorizou alerta no lugar de bloqueio')->call('save')->assertHasNoErrors();

        $this->assertSame('ALR', $req->fresh()->efeito);
        $this->assertTrue($req->fresh()->personalizado, 'editar item do template o marca como personalizado');
        $log = Activity::query()->where('description', 'Catalogo: edicao sensivel')->latest()->first();
        $this->assertNotNull($log);
        $this->assertSame('Fabricante autorizou alerta no lugar de bloqueio', $log->properties['justificativa']);
        $this->assertSame('IMP', $log->properties['mudancas'][0]['antes']);
        $this->assertSame('ALR', $log->properties['mudancas'][0]['depois']);
        $this->assertSame((string) $this->admin->id, (string) $log->causer_id);
    }

    public function test_tela_salva_edicao_nao_sensivel_sem_justificativa(): void
    {
        $t = Livewire::test(EditCatalogoGrupo::class, ['record' => $this->grupo->getKey()]);

        $t->set('data.description', 'Nova descricao')->call('save')->assertHasNoErrors();

        $this->assertSame('Nova descricao', $this->grupo->fresh()->description);
        $this->assertTrue($this->grupo->fresh()->personalizado);
        $this->assertSame(0, Activity::query()->where('description', 'Catalogo: edicao sensivel')->count());
    }

    public function test_remover_requisito_obrigatorio_pela_tela_exige_justificativa(): void
    {
        $req = $this->req('inspecao-vaso-pressao');
        $t = Livewire::test(EditCatalogoGrupo::class, ['record' => $this->grupo->getKey()]);
        $requisitos = $t->get('data.requisitos');
        unset($requisitos['record-'.$req->id]);

        $t->set('data.requisitos', $requisitos)->call('save')->assertHasErrors(['data.justificativa']);
        $this->assertNotNull(CatalogoRequisito::withoutGlobalScopes()->find($req->id));
    }

    // ---------------------------------------------------------------- tela de lista

    public function test_lista_e_instalar_catalogo_padrao_pela_tela(): void
    {
        $outro = $this->novoTenant(true);
        $this->actingAs($this->novoUsuario($outro, admin: true));

        Livewire::test(ListCatalogoGrupos::class)->assertSuccessful()->callAction('instalar');

        $this->assertSame(1, AssetCategory::withoutGlobalScopes()->where('tenant_id', $outro->id)->where('slug', 'compressor')->count());
    }

    public function test_lista_nao_mostra_grupos_de_outro_tenant(): void
    {
        $outro = $this->novoTenant(true);
        app(CatalogoInstalador::class)->instalar($outro);
        $alheio = AssetCategory::withoutGlobalScopes()->where('tenant_id', $outro->id)->firstOrFail();

        Livewire::test(ListCatalogoGrupos::class)->assertCanSeeTableRecords([$this->grupo])->assertCanNotSeeTableRecords([$alheio]);
    }

    public function test_biblioteca_lista_e_arquiva_pela_tela(): void
    {
        $item = CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'cinto')->firstOrFail();

        Livewire::test(ListCatalogoItens::class)->assertSuccessful()->assertCanSeeTableRecords([$item])
            ->callTableAction('remover', $item);

        $this->assertNull(CatalogoItem::withoutGlobalScopes()->withTrashed()->find($item->id), 'item nunca usado e excluido de vez');
    }

    public function test_desativar_item_impeditivo_pela_tela_exige_justificativa_e_loga(): void
    {
        $oleo = CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'oleo')->firstOrFail();
        $t = Livewire::test(EditCatalogoItem::class, ['record' => $oleo->getKey()]);

        $t->set('data.ativo', false)->call('save')->assertHasErrors(['data.justificativa']);
        $this->assertTrue($oleo->fresh()->ativo);

        $t->set('data.justificativa', 'Item substituido por outro no manual')->call('save')->assertHasNoErrors();

        $this->assertFalse($oleo->fresh()->ativo);
        $this->assertSame('Item substituido por outro no manual', Activity::query()->where('description', 'Catalogo: edicao sensivel')->latest()->first()->properties['justificativa']);
    }
}
