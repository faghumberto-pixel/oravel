<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetNr13Specification;
use App\Models\Nr13Document;
use App\Models\Nr13Inspection;
use App\Models\Nr13InspectionPeriodicity;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Etapa 2 do módulo NR-13: só migrations + models (a UI do Filament, políticas, notificação e
 * o comando de vencimento entram na Etapa 3). Cobre: colunas/relações criadas corretamente,
 * extensão 1:1 do Asset (não uma entidade paralela), escopo por tenant e os helpers de
 * vencimento (mesmo contrato de EmployeeCertification/FleetVehicleDocument).
 *
 * DatabaseTransactions (e NÃO RefreshDatabase): config/database.php fixa 'default' => 'pgsql',
 * então o DB_CONNECTION=sqlite do phpunit.xml não vale e RefreshDatabase rodaria migrate:fresh
 * contra o banco real do ambiente.
 */
class Nr13ComplianceModelsTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = $this->makeTenant();
        $this->tenantB = $this->makeTenant();
    }

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano NR13 '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);

        return Tenant::create(['name' => 'Tenant '.uniqid(), 'slug' => 'nr13-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    private function makeAsset(Tenant $tenant): Asset
    {
        return Asset::forceCreate([
            'tenant_id' => $tenant->id,
            'name' => 'Caldeira '.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL,
        ]);
    }

    public function test_asset_ganha_a_especificacao_nr13_como_extensao_1_para_1_e_nao_entidade_paralela(): void
    {
        $asset = $this->makeAsset($this->tenantA);

        $spec = AssetNr13Specification::create([
            'tenant_id' => $this->tenantA->id,
            'asset_id' => $asset->id,
            'subject_to_nr13' => true,
            'tipo_equipamento' => AssetNr13Specification::TIPO_CALDEIRA,
            'categoria_risco' => 'A',
            'tag_nr13' => 'CALD-01',
        ]);

        $this->assertTrue($asset->nr13Specification()->exists());
        $this->assertSame($spec->id, $asset->fresh()->nr13Specification->id);
        $this->assertSame($asset->id, $spec->asset->id);
    }

    public function test_asset_id_e_unico_em_asset_nr13_specifications_forcando_o_1_para_1(): void
    {
        $asset = $this->makeAsset($this->tenantA);
        AssetNr13Specification::create([
            'tenant_id' => $this->tenantA->id, 'asset_id' => $asset->id,
            'tipo_equipamento' => AssetNr13Specification::TIPO_CALDEIRA,
        ]);

        $this->expectException(QueryException::class);
        AssetNr13Specification::create([
            'tenant_id' => $this->tenantA->id, 'asset_id' => $asset->id,
            'tipo_equipamento' => AssetNr13Specification::TIPO_VASO_PRESSAO,
        ]);
    }

    public function test_documentos_e_inspecoes_pertencem_ao_asset_e_aparecem_nas_relacoes(): void
    {
        $asset = $this->makeAsset($this->tenantA);

        $doc = Nr13Document::create([
            'tenant_id' => $this->tenantA->id, 'asset_id' => $asset->id,
            'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO,
            'data_emissao' => now()->subYear(), 'data_validade' => now()->addMonths(6),
        ]);
        $insp = Nr13Inspection::create([
            'tenant_id' => $this->tenantA->id, 'asset_id' => $asset->id,
            'tipo' => Nr13Inspection::TIPO_INTERNA, 'data_inspecao' => now()->subMonths(6),
            'data_proxima_inspecao' => now()->addMonths(6), 'resultado' => Nr13Inspection::RESULTADO_APROVADO,
        ]);

        $fresh = $asset->fresh();
        $this->assertSame([$doc->id], $fresh->nr13Documents->pluck('id')->all());
        $this->assertSame([$insp->id], $fresh->nr13Inspections->pluck('id')->all());
        $this->assertSame($asset->id, $doc->asset->id);
        $this->assertSame($asset->id, $insp->asset->id);
    }

    public function test_documento_vencido_a_vencer_e_valido(): void
    {
        $asset = $this->makeAsset($this->tenantA);

        $vencido = Nr13Document::create(['tenant_id' => $this->tenantA->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->subDay()]);
        $aVencer = Nr13Document::create(['tenant_id' => $this->tenantA->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->addDays(10)]);
        $valido = Nr13Document::create(['tenant_id' => $this->tenantA->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_CERTIFICADO_INSPECAO, 'data_validade' => now()->addDays(90)]);
        $semValidade = Nr13Document::create(['tenant_id' => $this->tenantA->id, 'asset_id' => $asset->id, 'tipo' => Nr13Document::TIPO_PRONTUARIO, 'data_validade' => null]);

        $this->assertTrue($vencido->isVencido());
        $this->assertFalse($vencido->isProximoVencimento(30));

        $this->assertFalse($aVencer->isVencido());
        $this->assertTrue($aVencer->isProximoVencimento(30));

        $this->assertFalse($valido->isVencido());
        $this->assertFalse($valido->isProximoVencimento(30));

        $this->assertFalse($semValidade->isVencido());
        $this->assertFalse($semValidade->isProximoVencimento(30));
    }

    public function test_inspecao_vencida_e_proxima_do_vencimento(): void
    {
        $asset = $this->makeAsset($this->tenantA);

        $vencida = Nr13Inspection::create(['tenant_id' => $this->tenantA->id, 'asset_id' => $asset->id, 'tipo' => Nr13Inspection::TIPO_INTERNA, 'data_inspecao' => now()->subYears(2), 'data_proxima_inspecao' => now()->subDay()]);
        $proxima = Nr13Inspection::create(['tenant_id' => $this->tenantA->id, 'asset_id' => $asset->id, 'tipo' => Nr13Inspection::TIPO_INTERNA, 'data_inspecao' => now()->subMonths(11), 'data_proxima_inspecao' => now()->addDays(15)]);

        $this->assertTrue($vencida->isVencida());
        $this->assertTrue($proxima->isProximaDoVencimento(30));
        $this->assertFalse($proxima->isVencida());
    }

    public function test_periodicidade_e_configuravel_e_unica_por_tenant_tipo_e_categoria(): void
    {
        Nr13InspectionPeriodicity::create([
            'tenant_id' => $this->tenantA->id, 'tipo_equipamento' => AssetNr13Specification::TIPO_CALDEIRA,
            'categoria_risco' => 'A', 'intervalo_meses' => 12,
        ]);

        // outro tenant pode configurar um valor DIFERENTE pro mesmo tipo/categoria -- é editável por tenant, não fixo.
        $configB = Nr13InspectionPeriodicity::create([
            'tenant_id' => $this->tenantB->id, 'tipo_equipamento' => AssetNr13Specification::TIPO_CALDEIRA,
            'categoria_risco' => 'A', 'intervalo_meses' => 24,
        ]);
        $this->assertSame(24, $configB->intervalo_meses);

        $this->expectException(QueryException::class);
        Nr13InspectionPeriodicity::create([
            'tenant_id' => $this->tenantA->id, 'tipo_equipamento' => AssetNr13Specification::TIPO_CALDEIRA,
            'categoria_risco' => 'A', 'intervalo_meses' => 40,
        ]);
    }

    public function test_escopo_por_tenant_um_tenant_nao_enxerga_documentos_do_outro(): void
    {
        $assetA = $this->makeAsset($this->tenantA);
        $assetB = $this->makeAsset($this->tenantB);
        Nr13Document::create(['tenant_id' => $this->tenantA->id, 'asset_id' => $assetA->id, 'tipo' => Nr13Document::TIPO_PRONTUARIO]);
        Nr13Document::create(['tenant_id' => $this->tenantB->id, 'asset_id' => $assetB->id, 'tipo' => Nr13Document::TIPO_PRONTUARIO]);

        $this->actingAs($this->makeAdminOf($this->tenantA));

        $this->assertSame(1, Nr13Document::count());
    }

    private function makeAdminOf(Tenant $tenant): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'nr13-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
    }

    public function test_saas_metadata_identifica_os_4_models_pro_registry_central(): void
    {
        $this->assertSame('conformidade_nr13', AssetNr13Specification::saasPermissionSlug());
        $this->assertSame('documento_nr13', Nr13Document::saasPermissionSlug());
        $this->assertSame('inspecao_nr13', Nr13Inspection::saasPermissionSlug());
        $this->assertSame('periodicidade_nr13', Nr13InspectionPeriodicity::saasPermissionSlug());
    }
}
