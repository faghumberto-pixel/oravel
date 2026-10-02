<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\ForkliftSpecification;
use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\InternalUnit;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AssetExcelImporter;
use App\Support\AssetImport\AssetImportColumns;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class AssetExcelImportTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(string $name = 'Importadora'): Tenant
    {
        $plan = Plan::create(['name' => 'Plano '.$name, 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        return Tenant::create(['name' => $name, 'slug' => str($name)->slug().'-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    /** @param list<array<string, mixed>> $rows linhas indexadas pelo título da coluna */
    private function sheet(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ativos').'.xlsx';
        $titles = array_column(AssetImportColumns::all(), 'title');
        $w = new Writer;
        $w->openToFile($path);
        $w->getCurrentSheet()->setName('Ativos');
        $w->addRow(Row::fromValues(array_column(AssetImportColumns::all(), 'group')));
        $w->addRow(Row::fromValues($titles));
        foreach ($rows as $r) {
            $w->addRow(Row::fromValues(array_map(fn ($t) => $r[$t] ?? null, $titles)));
        }
        $w->close();

        return $path;
    }

    public function test_importa_ativo_com_especificacao_categoria_e_unidade(): void
    {
        $tenant = $this->tenant();
        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz']);

        $r = app(AssetExcelImporter::class)->import($this->sheet([[
            'Nº Patrimônio' => 'EMP-1', 'Nome/Modelo' => 'Empilhadeira 2,5t', 'Fabricante' => 'Hyster', 'Categoria e Tipo' => 'Empilhadeira',
            'Capacidade' => '2,5', 'Unidade da Capacidade' => 'toneladas', 'Status Operacional' => 'em operação', 'Criticidade' => 'Alta',
            'Horímetro Atual' => 1250, 'Unidade/Filial Base' => 'matriz', 'Propulsão (Empilhadeira)' => 'Elétrica',
            'Capacidade de Carga (kg)' => 2500, 'Voltagem da Bateria' => '48V',
        ]]), $tenant);

        $this->assertSame([], $r['errors']);
        $this->assertSame(1, $r['created']);

        $a = Asset::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('patrimonio', 'EMP-1')->firstOrFail();
        $this->assertSame('Empilhadeira 2,5t', $a->name);
        $this->assertSame('operando', $a->status);
        $this->assertSame('high', $a->criticality);
        $this->assertEquals(2.5, (float) $a->capacity_value);
        $this->assertEquals(1250, (float) $a->last_horimetro);
        $this->assertSame($unit->id, $a->internal_unit_id);
        $this->assertSame('Empilhadeira', $a->asset_category);
        $this->assertNotNull($a->asset_category_id);

        $spec = ForkliftSpecification::withoutGlobalScopes()->where('asset_id', $a->id)->firstOrFail();
        $this->assertSame('eletrica', $spec->energy_type);
        $this->assertEquals(2500, (float) $spec->load_capacity_kg);
        $this->assertSame($tenant->id, $spec->tenant_id);
    }

    public function test_simulacao_nao_grava_nada(): void
    {
        $tenant = $this->tenant();
        $r = app(AssetExcelImporter::class)->import($this->sheet([['Nº Patrimônio' => 'X-1', 'Nome/Modelo' => 'Gerador', 'Categoria e Tipo' => 'Gerador']]), $tenant, false, true);

        $this->assertSame(1, $r['created']);
        $this->assertSame(0, Asset::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, AssetCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_existente_e_ignorado_ou_atualizado_sem_apagar_campos_em_branco(): void
    {
        $tenant = $this->tenant();
        $importer = app(AssetExcelImporter::class);
        $importer->import($this->sheet([['Nº Patrimônio' => 'A-1', 'Nome/Modelo' => 'Original', 'Fabricante' => 'Marca']]), $tenant);

        $ignored = $importer->import($this->sheet([['Nº Patrimônio' => 'A-1', 'Nome/Modelo' => 'Novo']]), $tenant);
        $this->assertSame(1, $ignored['skipped']);
        $this->assertSame('Original', Asset::withoutGlobalScopes()->where('patrimonio', 'A-1')->where('tenant_id', $tenant->id)->value('name'));

        $updated = $importer->import($this->sheet([['Nº Patrimônio' => 'A-1', 'Nome/Modelo' => 'Novo']]), $tenant, true);
        $this->assertSame(1, $updated['updated']);
        $a = Asset::withoutGlobalScopes()->where('patrimonio', 'A-1')->where('tenant_id', $tenant->id)->first();
        $this->assertSame('Novo', $a->name);
        $this->assertSame('Marca', $a->fabricante);
    }

    public function test_linhas_com_erro_sao_puladas_e_as_boas_gravadas(): void
    {
        $tenant = $this->tenant();
        $r = app(AssetExcelImporter::class)->import($this->sheet([
            ['Nº Patrimônio' => 'OK-1', 'Nome/Modelo' => 'Bom'],
            ['Nº Patrimônio' => 'BAD-1', 'Nome/Modelo' => 'Status ruim', 'Status Operacional' => 'Quebrado'],
            ['Nº Patrimônio' => 'BAD-2', 'Nome/Modelo' => null],
            ['Nº Patrimônio' => 'BAD-3', 'Nome/Modelo' => 'Sem filial', 'Unidade/Filial Base' => 'Inexistente'],
            ['Nº Patrimônio' => 'OK-1', 'Nome/Modelo' => 'Repetido'],
            ['Nº Patrimônio' => 'OK-2', 'Nome/Modelo' => 'Bom 2', 'Horímetro Atual' => 'abc'],
        ]), $tenant);

        $this->assertSame(1, $r['created']);
        $this->assertCount(5, $r['errors']);
        $this->assertSame(['OK-1'], Asset::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('patrimonio')->all());
    }

    public function test_categoria_de_outro_cliente_nao_e_reaproveitada(): void
    {
        $a = $this->tenant('Cliente A');
        $b = $this->tenant('Cliente B');
        $catA = AssetCategory::create(['tenant_id' => $a->id, 'name' => 'Guindaste']);

        app(AssetExcelImporter::class)->import($this->sheet([['Nº Patrimônio' => 'G-1', 'Nome/Modelo' => 'G', 'Categoria e Tipo' => 'guindaste']]), $b);

        $asset = Asset::withoutGlobalScopes()->where('tenant_id', $b->id)->firstOrFail();
        $this->assertNotSame($catA->id, $asset->asset_category_id);
        $this->assertSame($b->id, AssetCategory::withoutGlobalScopes()->find($asset->asset_category_id)->tenant_id);
    }

    public function test_modelo_para_download_e_reconhecido_e_sem_colunas_financeiras(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'modelo').'.xlsx';
        AssetImportColumns::writeTemplate($path);
        $r = app(AssetExcelImporter::class)->import($path, $this->tenant());

        $this->assertSame([], $r['errors']);
        $this->assertSame(0, $r['created']);
        $titles = implode('|', array_column(AssetImportColumns::all(), 'title'));
        foreach (['Valor', 'Residual', 'Vida Útil', 'Custo', 'Depreciação'] as $financeiro) {
            $this->assertStringNotContainsString($financeiro, $titles);
        }
    }

    public function test_acao_da_tela_importa_a_planilha_enviada_pelo_admin_do_cliente(): void
    {
        $tenant = $this->tenant('Topmixx teste');
        $tenant->plan->update(['features' => [Asset::saasFeatureKey()]]);
        $user = User::create(['name' => 'Admin', 'email' => 'adm-'.uniqid().'@teste.com', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $file = UploadedFile::fake()->createWithContent('ativos.xlsx', file_get_contents($this->sheet([['Nº Patrimônio' => 'UI-1', 'Nome/Modelo' => 'Pela tela']])));

        Livewire::test(ListAssets::class)
            ->callAction('importarExcel', ['arquivo' => $file, 'simular' => false, 'atualizar' => false])
            ->assertHasNoActionErrors();

        $this->assertSame($tenant->id, Asset::withoutGlobalScopes()->where('patrimonio', 'UI-1')->value('tenant_id'));
    }
}
