<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\PlatformSpecification;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Antes desta feature, nao havia campo nenhum pra altura de trabalho,
 * altura de plataforma, alcance horizontal ou peso operacional -- so' o
 * par generico capacity_value/capacity_unit do Asset, que so' comporta
 * uma dimensao por vez.
 */
class PlatformSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_specification_saves_all_technical_fields_1_to_1_with_asset(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Plataforma '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_asset_platform_specifications'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Plataforma '.uniqid(), 'slug' => 'tenant-plataforma-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Plataforma Tesoura Teste',
            'asset_category' => 'Plataforma Elevatória Tesoura', 'status' => Asset::STATUS_DISPONIVEL,
        ]);

        $spec = PlatformSpecification::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'platform_type' => PlatformSpecification::TYPE_TESOURA,
            'energy_type' => PlatformSpecification::ENERGY_ELETRICA,
            'working_height_m' => 12.5,
            'platform_height_m' => 10.5,
            'horizontal_outreach_m' => 1.2,
            'platform_capacity_kg' => 450,
            'operational_weight_kg' => 3200,
        ]);

        $fresh = $asset->fresh()->platformSpecification;

        $this->assertNotNull($fresh);
        $this->assertSame($spec->id, $fresh->id);
        $this->assertSame(PlatformSpecification::TYPE_TESOURA, $fresh->platform_type);
        $this->assertEquals(12.5, (float) $fresh->working_height_m);
        $this->assertEquals(10.5, (float) $fresh->platform_height_m);
        $this->assertEquals(1.2, (float) $fresh->horizontal_outreach_m);
        $this->assertEquals(450, (float) $fresh->platform_capacity_kg);
        $this->assertEquals(3200, (float) $fresh->operational_weight_kg);
    }

    public function test_platform_specification_is_unique_per_asset(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Plataforma Unica '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_asset_platform_specifications'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Plataforma Unica '.uniqid(), 'slug' => 'tenant-plataforma-unica-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Plataforma Teste', 'asset_category' => 'Plataforma Elevatória Tesoura', 'status' => Asset::STATUS_DISPONIVEL]);

        PlatformSpecification::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'platform_type' => PlatformSpecification::TYPE_TESOURA]);

        $this->expectException(QueryException::class);

        PlatformSpecification::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'platform_type' => PlatformSpecification::TYPE_ARTICULADA]);
    }
}
