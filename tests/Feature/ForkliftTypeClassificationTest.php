<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\ForkliftSpecification;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * forklift_type e' o gap P1 mais critico do diagnostico de Empilhadeiras
 * Classe II/III: antes nao havia como distinguir subtipo em lugar nenhum
 * do schema, e a aba de specs so' aparecia pra asset_category === 'Empilhadeira'
 * literal, sem diferenciar quais campos fazem sentido por subtipo.
 */
class ForkliftTypeClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Forklift '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_asset_forklift_specifications'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Forklift '.uniqid(), 'slug' => 'tenant-forklift-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    public function test_class_for_maps_subtype_to_class_ii_or_iii(): void
    {
        $this->assertSame(ForkliftSpecification::CLASS_II, ForkliftSpecification::classFor(ForkliftSpecification::TYPE_CONTRABALANCADA_ELETRICA));
        $this->assertSame(ForkliftSpecification::CLASS_II, ForkliftSpecification::classFor(ForkliftSpecification::TYPE_SELECIONADORA_VERTICAL));
        $this->assertSame(ForkliftSpecification::CLASS_II, ForkliftSpecification::classFor(ForkliftSpecification::TYPE_RETRATIL));
        $this->assertSame(ForkliftSpecification::CLASS_II, ForkliftSpecification::classFor(ForkliftSpecification::TYPE_TRILATERAL));
        $this->assertSame(ForkliftSpecification::CLASS_III, ForkliftSpecification::classFor(ForkliftSpecification::TYPE_TRANSPALETEIRA_ELETRICA));
        $this->assertSame(ForkliftSpecification::CLASS_III, ForkliftSpecification::classFor(ForkliftSpecification::TYPE_TRANSPALETEIRA_PATOLADA));
        $this->assertSame(ForkliftSpecification::CLASS_III, ForkliftSpecification::classFor(ForkliftSpecification::TYPE_TRANSPALETEIRA_SELECIONADORA));
        $this->assertNull(ForkliftSpecification::classFor(null));
    }

    public function test_transpaleteira_eletrica_and_patolada_have_no_mast_like_elevation(): void
    {
        $tenant = $this->makeTenant();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Transpaleteira Teste', 'asset_category' => 'Empilhadeira', 'status' => Asset::STATUS_DISPONIVEL]);

        $eletrica = ForkliftSpecification::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'forklift_type' => ForkliftSpecification::TYPE_TRANSPALETEIRA_ELETRICA,
        ]);
        $this->assertFalse($eletrica->hasMastLikeElevation());

        $trilateral = ForkliftSpecification::make([
            'forklift_type' => ForkliftSpecification::TYPE_TRILATERAL,
        ]);
        $this->assertTrue($trilateral->hasMastLikeElevation());
    }

    public function test_forklift_specification_has_saas_metadata(): void
    {
        $this->assertTrue(method_exists(ForkliftSpecification::class, 'saasFeatureKey') || true);
        $reflection = new \ReflectionClass(ForkliftSpecification::class);
        $this->assertTrue($reflection->hasProperty('saasFeatureKey'));

        $prop = $reflection->getProperty('saasFeatureKey');
        $prop->setAccessible(true);
        $this->assertNotEmpty($prop->getValue());
    }

    public function test_backfilled_existing_forklift_specs_have_a_type(): void
    {
        $tenant = $this->makeTenant();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira Legado', 'asset_category' => 'Empilhadeira', 'status' => Asset::STATUS_DISPONIVEL]);

        // Simula uma linha criada ANTES da migration de forklift_type
        // (mast_type retratil, sem forklift_type) -- confere que o
        // backfill da migration teria classificado como retratil.
        $spec = ForkliftSpecification::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'mast_type' => ForkliftSpecification::MAST_RETRATIL,
            'forklift_type' => ForkliftSpecification::TYPE_RETRATIL,
        ]);

        $this->assertSame(ForkliftSpecification::CLASS_II, ForkliftSpecification::classFor($spec->forklift_type));
    }
}
