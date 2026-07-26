<?php

namespace Tests\Feature;

use App\Models\Depot;
use App\Models\InternalUnit;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalUnitDepotSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Unidade '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_internal_units', 'tabela_depots'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Unidade '.uniqid(), 'slug' => 'tenant-unidade-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    public function test_saving_a_unit_with_coordinates_creates_a_linked_depot(): void
    {
        $tenant = $this->makeTenant();

        $unit = InternalUnit::create([
            'tenant_id' => $tenant->id,
            'name' => 'Filial Sul',
            'code' => 'FILIAL-SUL',
            'type' => 'filial',
            'address' => 'Rua Teste, 100',
            'city' => 'Curitiba',
            'state' => 'PR',
            'cep' => '80000-000',
            'latitude' => -25.4284,
            'longitude' => -49.2733,
        ]);

        $depot = Depot::where('internal_unit_id', $unit->id)->first();

        $this->assertNotNull($depot);
        $this->assertSame('Filial Sul', $depot->name);
        $this->assertSame($tenant->id, $depot->tenant_id);
        $this->assertEqualsWithDelta(-25.4284, (float) $depot->latitude, 0.0001);
        $this->assertEqualsWithDelta(-49.2733, (float) $depot->longitude, 0.0001);
    }

    public function test_unit_without_coordinates_does_not_create_a_depot(): void
    {
        $tenant = $this->makeTenant();

        $unit = InternalUnit::create([
            'tenant_id' => $tenant->id,
            'name' => 'Matriz Sem Endereço',
            'code' => 'MATRIZ',
            'type' => 'matriz',
        ]);

        $this->assertNull(Depot::where('internal_unit_id', $unit->id)->first());
    }

    public function test_updating_unit_address_updates_the_linked_depot_without_duplicating(): void
    {
        $tenant = $this->makeTenant();

        $unit = InternalUnit::create([
            'tenant_id' => $tenant->id,
            'name' => 'Filial Norte',
            'code' => 'FILIAL-NORTE',
            'type' => 'filial',
            'latitude' => -3.7327,
            'longitude' => -38.5267,
        ]);

        $unit->update(['latitude' => -3.8000, 'longitude' => -38.6000]);

        $this->assertSame(1, Depot::where('internal_unit_id', $unit->id)->count());
        $depot = Depot::where('internal_unit_id', $unit->id)->first();
        $this->assertEqualsWithDelta(-3.8000, (float) $depot->latitude, 0.0001);
    }
}
