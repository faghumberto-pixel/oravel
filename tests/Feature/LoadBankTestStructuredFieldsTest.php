<?php

namespace Tests\Feature;

use App\Livewire\EquipmentMovementMobile;
use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gap do diagnostico de Geradores: load_bank_tested/load_bank_notes so'
 * tinham boolean + texto livre, sem estrutura pra nivel de carga aplicado,
 * duracao do teste ou temperatura.
 */
class LoadBankTestStructuredFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_load_bank_test_persists_structured_fields(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Banco Carga '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_equipment_movements'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Banco Carga '.uniqid(), 'slug' => 'tenant-banco-carga-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador 150kVA', 'status' => Asset::STATUS_DISPONIVEL]);
        $maintenanceOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Mobilização de teste', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $component->set('loadBankTested', true)
            ->set('loadBankPercentage', 100)
            ->set('loadBankDurationMinutes', 30)
            ->set('loadBankTemperature', 42.5)
            ->set('loadBankNotes', 'Sem anomalias')
            ->call('saveLoadBankTest');

        $movement = $component->instance()->equipmentMovement->fresh();

        $this->assertTrue($movement->load_bank_tested);
        $this->assertSame(100, $movement->load_bank_percentage);
        $this->assertSame(30, $movement->load_bank_duration_minutes);
        $this->assertEquals(42.5, (float) $movement->load_bank_temperature_c);
        $this->assertSame('Sem anomalias', $movement->load_bank_notes);
    }

    public function test_load_bank_percentage_rejects_out_of_range_values(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Banco Carga Invalido '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_equipment_movements'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Banco Carga Invalido '.uniqid(), 'slug' => 'tenant-banco-carga-invalido-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'status' => Asset::STATUS_DISPONIVEL]);
        $maintenanceOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Mobilização de teste', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        $this->actingAs($admin);

        Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ])
            ->set('loadBankPercentage', 150)
            ->call('saveLoadBankTest')
            ->assertHasErrors(['loadBankPercentage']);
    }
}
