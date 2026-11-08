<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\RelationManagers\CumprimentosRelationManager;
use App\Filament\Widgets\AptidaoFrotaWidget;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoAtributo;
use App\Models\CatalogoCumprimento;
use App\Models\CatalogoItem;
use App\Models\CatalogoRequisito;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoInstalador;
use App\Support\Catalogo\CampoTecnicoForm;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * G1 fase 4: cadastro do ativo por grupo (campos tecnicos), aptidao/cumprimentos no ativo, widget e "cadastro incompleto".
 * SEGURANCA: so roda em banco com "test" no nome.
 */
class G1AtivoCadastroTest extends TestCase
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

        [$this->tenant, $this->admin] = $this->novoTenantComAdmin(true);
        app(CatalogoInstalador::class)->instalar($this->tenant);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @return array{0: Tenant, 1: User} */
    private function novoTenantComAdmin(bool $comChave): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ($comChave ? [CatalogoItem::FEATURE_KEY => true] : []) + ['tabela_assets' => true]]);
        $t = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'g1c-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'Ana', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return [$t, $u];
    }

    private function g(string $slug): AssetCategory
    {
        return AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', $slug)->firstOrFail();
    }

    private function ativo(?string $grupo, array $extra = []): Asset
    {
        return Asset::factory()->create(array_merge(['tenant_id' => $this->tenant->id, 'asset_category_id' => $grupo ? $this->g($grupo)->id : null, 'horimetro_atual' => 100], $extra));
    }

    private function nomes(string $grupo): array
    {
        return array_map(fn ($c) => $c->getName(), CampoTecnicoForm::campos($this->g($grupo)->id));
    }

    // ------------------------------------------------------------ campos por grupo

    public function test_cada_grupo_mostra_so_os_seus_campos_tecnicos(): void
    {
        $gerador = $this->nomes('gerador');
        $guindaste = $this->nomes('guindaste');
        $compressor = $this->nomes('compressor');

        $this->assertContains('atributos_tecnicos.kva', $gerador);
        $this->assertNotContains('atributos_tecnicos.tabela-carga', $gerador);
        $this->assertContains('atributos_tecnicos.tabela-carga', $guindaste);
        $this->assertNotContains('atributos_tecnicos.kva', $guindaste);
        $this->assertContains('atributos_tecnicos.pressao-maxima', $compressor);
        $this->assertNotContains('atributos_tecnicos.pressao-maxima', $gerador);
        $this->assertNotContains('atributos_tecnicos.pressao-maxima', $guindaste);
    }

    public function test_tipos_de_campo(): void
    {
        $campos = collect(CampoTecnicoForm::campos($this->g('guindaste')->id))->keyBy(fn ($c) => $c->getName());
        $this->assertInstanceOf(TextInput::class, $campos['atributos_tecnicos.capacidade-maxima']);
        $this->assertInstanceOf(Toggle::class, $campos['atributos_tecnicos.autopropelido']);

        $fases = collect(CampoTecnicoForm::campos($this->g('gerador')->id))->keyBy(fn ($c) => $c->getName())['atributos_tecnicos.fases'];
        $this->assertInstanceOf(Select::class, $fases);
    }

    public function test_campos_do_grupo_seguem_a_edicao_do_catalogo_do_tenant(): void
    {
        $attr = CatalogoAtributo::withoutGlobalScopes()->where('grupo_id', $this->g('gerador')->id)->where('chave', 'kva')->firstOrFail();
        $attr->update(['ativo' => false]);

        $this->assertNotContains('atributos_tecnicos.kva', $this->nomes('gerador'));
    }

    public function test_secao_so_aparece_com_a_chave_ligada(): void
    {
        $this->assertTrue(CampoTecnicoForm::deveMostrar($this->g('gerador')->id));
        $this->assertFalse(CampoTecnicoForm::deveMostrar(null));

        [$off, $adminOff] = $this->novoTenantComAdmin(false);
        $this->actingAs($adminOff);
        $gOff = AssetCategory::withoutGlobalScopes()->create(['tenant_id' => $off->id, 'name' => 'X', 'slug' => 'x', 'is_active' => true]);

        $this->assertFalse(CampoTecnicoForm::deveMostrar($gOff->id));
    }

    public function test_campos_nao_vazam_entre_tenants(): void
    {
        [$outro, $adminOutro] = $this->novoTenantComAdmin(true);
        app(CatalogoInstalador::class)->instalar($outro);
        $alheio = AssetCategory::withoutGlobalScopes()->where('tenant_id', $outro->id)->where('slug', 'gerador')->firstOrFail();

        // usuario do tenant A nao enxerga campos do grupo do tenant B
        $this->assertSame([], CampoTecnicoForm::campos($alheio->id));
    }

    // ------------------------------------------------------------ tela do ativo

    public function test_editar_ativo_grava_os_campos_tecnicos_do_grupo(): void
    {
        $a = $this->ativo('gerador');

        Livewire::test(EditAsset::class, ['record' => $a->getKey()])
            ->fillForm(['atributos_tecnicos' => ['kva' => 180, 'fases' => 'trifasico']])
            ->call('save')
            ->assertHasNoFormErrors();

        $f = Asset::withoutGlobalScopes()->find($a->id);
        $this->assertEquals(180, $f->atributos_tecnicos['kva']);
        $this->assertSame('trifasico', $f->atributos_tecnicos['fases']);
    }

    public function test_campo_obrigatorio_vazio_so_avisa_e_nao_bloqueia_o_cadastro(): void
    {
        $a = $this->ativo('gerador');

        Livewire::test(EditAsset::class, ['record' => $a->getKey()])->call('save')->assertHasNoFormErrors();

        $avisos = Asset::withoutGlobalScopes()->find($a->id)->aptidao_motivos['avisos'] ?? [];
        $this->assertNotEmpty($avisos);
        $this->assertStringContainsString('Potencia', implode(' ', $avisos));
    }

    // ------------------------------------------------------------ cadastro incompleto + coluna/filtro

    public function test_ativo_sem_grupo_aparece_como_cadastro_incompleto(): void
    {
        $a = $this->ativo(null);

        $this->assertSame('cadastro_incompleto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);
    }

    public function test_coluna_e_filtro_de_aptidao_so_com_a_chave_ligada(): void
    {
        $tabela = fn () => Livewire::test(AssetResource\Pages\ListAssets::class)->instance()->getTable();

        $this->assertTrue($tabela()->getFilter('aptidao_status')->isVisible());
        $this->assertTrue($tabela()->getColumn('aptidao_status')->isVisible());

        [$off, $adminOff] = $this->novoTenantComAdmin(false);
        $this->actingAs($adminOff);

        // filtro escondido (visible=false) nem entra na lista de filtros; a coluna fica invisivel
        $this->assertNull($tabela()->getFilter('aptidao_status'));
        $coluna = $tabela()->getColumn('aptidao_status');
        $this->assertTrue($coluna === null || ! $coluna->isVisible());
    }

    // ------------------------------------------------------------ cumprimentos no ativo

    public function test_registrar_cumprimento_pela_tela_recalcula_a_aptidao(): void
    {
        $a = $this->ativo('empilhadeira');
        $freios = CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $this->g('empilhadeira')->id)->where('slug', 'freios')->firstOrFail();

        Livewire::test(CumprimentosRelationManager::class, ['ownerRecord' => $a, 'pageClass' => EditAsset::class])
            ->callTableAction('create', data: ['alvo' => 'req:'.$freios->id, 'resultado' => 'reprovado', 'realizado_em' => now()->toDateTimeString()])
            ->assertHasNoTableActionErrors();

        $c = CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->firstOrFail();
        $this->assertSame('reprovado', $c->resultado);
        $this->assertSame('IMP', $c->snapshot['efeito']);
        $this->assertSame((string) $this->admin->id, (string) $c->registrado_por);
        $this->assertSame('inapto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);
    }

    public function test_cumprimento_so_aceita_alvos_do_grupo_do_ativo(): void
    {
        $a = $this->ativo('empilhadeira');
        $doGerador = CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $this->g('gerador')->id)->where('slug', 'aterramento')->firstOrFail();

        Livewire::test(CumprimentosRelationManager::class, ['ownerRecord' => $a, 'pageClass' => EditAsset::class])
            ->callTableAction('create', data: ['alvo' => 'req:'.$doGerador->id, 'resultado' => 'reprovado', 'realizado_em' => now()->toDateTimeString()])
            ->assertHasTableActionErrors(['alvo']);

        $this->assertSame(0, CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->count());
    }

    public function test_historico_de_cumprimentos_nao_tem_editar_nem_excluir(): void
    {
        $a = $this->ativo('empilhadeira');
        Livewire::test(CumprimentosRelationManager::class, ['ownerRecord' => $a, 'pageClass' => EditAsset::class])
            ->assertTableActionDoesNotExist('edit')->assertTableActionDoesNotExist('delete');
    }

    public function test_aba_de_cumprimentos_so_com_chave_ligada_e_permissao(): void
    {
        $a = $this->ativo('gerador');
        $this->assertTrue(CumprimentosRelationManager::canViewForRecord($a, EditAsset::class));

        [$off, $adminOff] = $this->novoTenantComAdmin(false);
        $this->actingAs($adminOff);
        $aOff = Asset::factory()->create(['tenant_id' => $off->id]);
        $this->assertFalse(CumprimentosRelationManager::canViewForRecord($aOff, EditAsset::class));
    }

    // ------------------------------------------------------------ widget

    public function test_widget_de_aptidao_conta_e_alerta_cadastro_incompleto(): void
    {
        $this->ativo(null);
        $this->ativo(null);
        $this->ativo('gerador');

        $this->assertTrue(AptidaoFrotaWidget::canView());
        Livewire::test(AptidaoFrotaWidget::class)->assertSee('Cadastro incompleto')->assertSee('Ativos sem grupo de equipamento');
    }

    public function test_widget_some_com_a_chave_desligada(): void
    {
        [$off, $adminOff] = $this->novoTenantComAdmin(false);
        $this->actingAs($adminOff);

        $this->assertFalse(AptidaoFrotaWidget::canView());
    }
}
