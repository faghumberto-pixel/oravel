<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\CreateAsset;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\ChecklistGroup;
use App\Models\CkTemplate;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Checklists\ChecklistAplicavel;
use App\Services\Checklists\ChecklistInstalador;
use App\Services\Checklists\ChecklistsDaOs;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Na OS: o campo "Grupo do Ativo" explica de onde vem o checklist e a secao lista os itens a conferir. */
class ChecklistsOsGrupoEItensTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('banco nao descartavel');
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $plano = Plan::create(['name' => 'P'.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [CatalogoItem::FEATURE_KEY => true, CkTemplate::FEATURE_KEY => true, 'tabela_ck_execucoes' => true, 'tabela_assets' => true]]);
        $this->tenant = Tenant::create(['name' => 'T'.uniqid(), 'slug' => 'os-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'Ana', 'email' => uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        app(CatalogoInstalador::class)->instalar($this->tenant);
        app(ChecklistInstalador::class)->instalar($this->tenant);
        $this->actingAs($u->refresh());
    }

    private function ativo(?string $grupo, array $extra = []): Asset
    {
        $cat = $grupo ? AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', $grupo)->firstOrFail() : null;

        return Asset::factory()->create($extra + ['tenant_id' => $this->tenant->id, 'asset_category_id' => $cat?->id, 'checklist_group_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);
    }

    public function test_grupo_do_ativo_mostra_de_onde_vem_o_checklist_em_vez_de_sem_grupo_definido(): void
    {
        $svc = app(ChecklistsDaOs::class);

        $compressor = $this->ativo('compressor');
        $this->assertStringStartsWith('Compressor', $svc->textoGrupoDoAtivo($compressor));
        $this->assertStringContainsString('grupo de equipamento', $svc->textoGrupoDoAtivo($compressor));

        $grupo = ChecklistGroup::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Compressores de obra']);
        $comGrupo = $this->ativo('compressor', ['checklist_group_id' => $grupo->id]);
        $this->assertSame('Compressores de obra', $svc->textoGrupoDoAtivo($comGrupo->load('checklistGroup')));

        $this->assertSame('Sem grupo definido', $svc->textoGrupoDoAtivo($this->ativo(null)));
        $this->assertSame('Sem grupo definido', $svc->textoGrupoDoAtivo(null));
    }

    public function test_categoria_sem_checklist_aplicavel_continua_dizendo_sem_grupo_definido(): void
    {
        CkTemplate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', 'compressor-diario')->update(['ativo' => false]);

        $this->assertSame('Sem grupo definido', app(ChecklistsDaOs::class)->textoGrupoDoAtivo($this->ativo('compressor')));
    }

    public function test_a_secao_traz_os_itens_do_checklist_por_secao_com_o_aviso_de_critico(): void
    {
        $a = $this->ativo('compressor');
        $dados = app(ChecklistsDaOs::class)->paraAtivo($a->id);
        $modelo = $dados['modelos'][0];

        $this->assertNotEmpty($modelo['secoes']);
        $total = array_sum(array_map(fn ($s) => count($s['itens']), $modelo['secoes']));
        $this->assertSame($modelo['itens'], $total, 'a lista mostra todos os itens contados');
        $this->assertTrue(collect($modelo['secoes'])->flatMap(fn ($s) => $s['itens'])->contains('critico', true));

        $html = view('filament.forms.checklists-da-os', ['dados' => $dados, 'os' => null])->render();
        $this->assertStringContainsString('data-testid="itens-do-checklist"', $html);
        $this->assertStringContainsString("Ver os {$modelo['itens']} itens a conferir", $html);
        $primeiro = $modelo['secoes'][0];
        $this->assertStringContainsString(e($primeiro['titulo']), $html);
        $this->assertStringContainsString(e($primeiro['itens'][0]['descricao']), $html);
        $this->assertStringContainsString('crítico', $html);
    }

    public function test_instalar_cria_um_grupo_de_checklist_por_grupo_de_equipamento_sem_duplicar_nem_mexer_em_ativo(): void
    {
        $slugs = collect(app(ChecklistInstalador::class)->carregar())->map(fn ($d) => $d['template']['grupo'] ?? null)->filter()->unique()->all();
        $nomes = AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereIn('slug_template', $slugs)->pluck('name')->all();
        $this->assertNotEmpty($nomes);
        $grupos = fn () => ChecklistGroup::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->pluck('name')->all();

        $this->assertEqualsCanonicalizing($nomes, $grupos(), 'a lista de Grupo de checklist ja vem com os grupos de equipamento');

        $a = $this->ativo('compressor');
        app(ChecklistInstalador::class)->instalar($this->tenant);
        $this->assertCount(count($nomes), $grupos(), 'reinstalar nao duplica');
        $this->assertNull($a->fresh()->checklist_group_id, 'ativo nao e mexido');
        $this->assertNotEmpty(app(ChecklistAplicavel::class)->paraAtivo($a->fresh()), 'o checklist continua valendo pelo grupo de equipamento');

        ChecklistGroup::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', 'Compressor')->update(['name' => 'Compressores da obra']);
        app(ChecklistInstalador::class)->instalar($this->tenant);
        $this->assertContains('Compressor', $grupos(), 'grupo renomeado pelo cliente volta a existir (nome do equipamento) sem apagar o dele');
        $this->assertContains('Compressores da obra', $grupos());
    }

    public function test_cadastro_de_ativo_ja_escolhe_o_grupo_de_checklist_ao_escolher_o_grupo_de_equipamento(): void
    {
        $cat = AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug_template', 'compressor')->firstOrFail();
        $grupo = ChecklistGroup::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('name', $cat->name)->firstOrFail();

        Livewire::test(CreateAsset::class)->fillForm(['asset_category_id' => $cat->id])->assertFormSet(['checklist_group_id' => $grupo->id]);
    }

    public function test_cliente_novo_dos_pacotes_com_equipamento_ou_veiculo_nasce_com_catalogo_checklists_e_grupos(): void
    {
        foreach (['locadora', 'manutencao', 'servicos', 'logistica'] as $pacote) {
            $features = Pacotes::features($pacote);

            foreach (['tabela_catalogo_equipamentos', 'tabela_ck_templates', 'tabela_ck_execucoes'] as $chave) {
                $this->assertContains($chave, $features, "pacote $pacote deve incluir $chave");
            }

            $plano = Plan::create(['name' => 'V '.$pacote.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => array_fill_keys($features, true)]);
            $t = Tenant::create(['name' => 'N '.uniqid(), 'slug' => 'n-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
            TenantProvisioner::provision($t, ['name' => 'Adm', 'email' => uniqid().'@oravel.com.br', 'password' => 'Senha-forte-123']);

            $this->assertGreaterThan(0, CkTemplate::withoutGlobalScopes()->where('tenant_id', $t->id)->count(), "$pacote: checklists instalados");
            $this->assertGreaterThan(0, ChecklistGroup::withoutGlobalScopes()->where('tenant_id', $t->id)->count(), "$pacote: grupos de checklist criados");
        }
    }
}
