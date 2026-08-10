<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\GeneratorSpecification;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gap do diagnostico de Geradores: nenhum campo de tensao/voltagem,
 * capacidade de tanque ou tipo de partida existia em lugar nenhum.
 */
class GeneratorSpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_specification_saves_all_technical_fields_1_to_1_with_asset(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Gerador '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_asset_generator_specifications'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Gerador '.uniqid(), 'slug' => 'tenant-gerador-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador 150kVA', 'asset_category' => 'Gerador', 'status' => Asset::STATUS_DISPONIVEL]);

        GeneratorSpecification::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'voltage_type' => GeneratorSpecification::VOLTAGE_TRIFASICO,
            'voltage' => '220V/380V',
            'fuel_tank_capacity_l' => 200,
            'starter_type' => GeneratorSpecification::STARTER_ELETRICA,
        ]);

        $fresh = $asset->fresh()->generatorSpecification;

        $this->assertNotNull($fresh);
        $this->assertSame(GeneratorSpecification::VOLTAGE_TRIFASICO, $fresh->voltage_type);
        $this->assertSame('220V/380V', $fresh->voltage);
        $this->assertEquals(200, (float) $fresh->fuel_tank_capacity_l);
        $this->assertSame(GeneratorSpecification::STARTER_ELETRICA, $fresh->starter_type);
    }
}
