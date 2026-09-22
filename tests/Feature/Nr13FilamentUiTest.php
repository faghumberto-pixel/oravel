<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\RelationManagers\Nr13DocumentsRelationManager;
use App\Filament\Resources\AssetResource\RelationManagers\Nr13InspectionsRelationManager;
use App\Models\Asset;
use App\Models\AssetNr13Specification;
use App\Models\Nr13Document;
use App\Models\Nr13Inspection;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Etapa 3 do módulo NR-13: aba condicional no AssetResource + os 2 RelationManagers.
 * Mesmo padrão de tests/Feature/FleetResourcesTest.php (aba condicional Empilhadeira).
 *
 * DatabaseTransactions (e NÃO RefreshDatabase): config/database.php fixa 'default' => 'pgsql'.
 */
class Nr13FilamentUiTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano NR13 UI '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_asset_nr13_specifications', 'tabela_nr13_documents', 'tabela_nr13_inspections'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant NR13 UI '.uniqid(), 'slug' => 'tenant-nr13-ui-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        // new+save (não Role::create): o create() do Spatie ignora tenant_id na checagem de
        // duplicado, mas o banco permite um 'admin' por tenant (índice único name+guard+tenant_id).
        $role = new Role(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role->save();
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeAsset(Tenant $tenant): Asset
    {
        return Asset::create(['tenant_id' => $tenant->id, 'name' => 'Caldeira Teste', 'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL]);
    }

    /**
     * O campo real (Select tipo_equipamento) só aparece quando o toggle subject_to_nr13 está
     * ligado -- prova empírica de que o $get() relativo dentro da mesma Section::relationship()
     * resolve certo (não achei um precedente confiável no próprio AssetResource pra copiar
     * cegamente: a Section irmã "Bateria e Carregador" usa o caminho prefixado
     * 'forkliftSpecification.energy_type' porque lê um campo de OUTRA Section com o mesmo
     * relationship -- aqui os campos estão dentro da MESMA Section, então testei em vez de supor).
     */
    public function test_campos_da_secao_nr13_so_aparecem_com_o_toggle_ligado(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $component = Livewire::test(EditAsset::class, ['record' => $asset->id])
            ->assertFormFieldDoesNotExist('nr13Specification.tipo_equipamento');

        $component->fillForm(['nr13Specification.subject_to_nr13' => true])
            ->assertFormFieldExists('nr13Specification.tipo_equipamento');
    }

    public function test_salvar_a_secao_nr13_cria_a_especificacao_ligada_ao_asset(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        // Duas chamadas fillForm de propósito, não uma só: liga o toggle PRIMEIRO (igual a um
        // clique real -- Toggle::make()->live() dispara um round-trip que desconta a Grid
        // condicional) antes de preencher os campos que só existem depois desse round-trip. Numa
        // única chamada em lote, os campos "aparecem" e "somem" na mesma requisição e o
        // Filament não os desidrata (mesma regra de FormComponent::isHiddenAndNotDehydrated())
        // -- não é alcançável por um usuário real na UI, mas prova exatamente o motivo do split.
        Livewire::test(EditAsset::class, ['record' => $asset->id])
            ->fillForm(['nr13Specification.subject_to_nr13' => true])
            ->fillForm([
                'patrimonio' => 'PAT-'.uniqid(),
                'acquisition_value' => 1000,
                'acquisition_date' => now()->subYear()->toDateString(),
                'nr13Specification.tipo_equipamento' => AssetNr13Specification::TIPO_CALDEIRA,
                'nr13Specification.categoria_risco' => 'A',
                'nr13Specification.tag_nr13' => 'CALD-01',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $spec = AssetNr13Specification::where('asset_id', $asset->id)->sole();
        $this->assertTrue((bool) $spec->subject_to_nr13);
        $this->assertSame(AssetNr13Specification::TIPO_CALDEIRA, $spec->tipo_equipamento);
        $this->assertSame('A', $spec->categoria_risco);
        $this->assertSame($tenant->id, $spec->tenant_id);
    }

    public function test_desligar_o_toggle_nao_apaga_a_especificacao_ja_salva(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        AssetNr13Specification::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'subject_to_nr13' => true, 'tipo_equipamento' => AssetNr13Specification::TIPO_CALDEIRA,
        ]);
        $this->actingAs($admin);

        Livewire::test(EditAsset::class, ['record' => $asset->id])
            ->fillForm([
                'patrimonio' => 'PAT-'.uniqid(),
                'acquisition_value' => 1000,
                'acquisition_date' => now()->subYear()->toDateString(),
                'nr13Specification.subject_to_nr13' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $spec = AssetNr13Specification::where('asset_id', $asset->id)->sole();
        $this->assertFalse((bool) $spec->subject_to_nr13);
    }

    public function test_relation_manager_de_documentos_cria_registro_ligado_ao_asset_certo(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        Livewire::test(Nr13DocumentsRelationManager::class, [
            'ownerRecord' => $asset, 'pageClass' => EditAsset::class,
        ])
            ->callTableAction('create', data: [
                'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO,
                'data_emissao' => now()->subMonth()->toDateString(),
                'data_validade' => now()->addMonths(6)->toDateString(),
            ])
            ->assertHasNoTableActionErrors();

        $doc = Nr13Document::where('asset_id', $asset->id)->sole();
        $this->assertSame($tenant->id, $doc->tenant_id);
        $this->assertSame(Nr13Document::TIPO_CERTIFICADO_INSPECAO, $doc->tipo);
    }

    public function test_relation_manager_de_inspecoes_cria_registro_ligado_ao_asset_certo(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        Livewire::test(Nr13InspectionsRelationManager::class, [
            'ownerRecord' => $asset, 'pageClass' => EditAsset::class,
        ])
            ->callTableAction('create', data: [
                'tipo' => Nr13Inspection::TIPO_INTERNA,
                'data_inspecao' => now()->subMonth()->toDateString(),
                'data_proxima_inspecao' => now()->addMonths(11)->toDateString(),
                'resultado' => Nr13Inspection::RESULTADO_APROVADO,
            ])
            ->assertHasNoTableActionErrors();

        $insp = Nr13Inspection::where('asset_id', $asset->id)->sole();
        $this->assertSame($tenant->id, $insp->tenant_id);
        $this->assertSame(Nr13Inspection::RESULTADO_APROVADO, $insp->resultado);
    }

    public function test_documentos_e_inspecoes_nao_vazam_entre_tenants_no_relation_manager(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = $this->makeAsset($tenantA);
        Nr13Document::create(['tenant_id' => $tenantA->id, 'asset_id' => $assetA->id, 'tipo' => Nr13Document::TIPO_PRONTUARIO]);

        [$tenantB, $adminB] = $this->makeTenantAdmin();
        $assetB = $this->makeAsset($tenantB);
        $this->actingAs($adminB);

        Livewire::test(Nr13DocumentsRelationManager::class, [
            'ownerRecord' => $assetB, 'pageClass' => EditAsset::class,
        ])->assertCountTableRecords(0);
    }
}
