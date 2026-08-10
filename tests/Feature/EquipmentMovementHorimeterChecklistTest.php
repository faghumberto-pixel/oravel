<?php

namespace Tests\Feature;

use App\Livewire\EquipmentMovementMobile;
use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\EquipmentMovementItemTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Antes desta feature, os itens "Horímetro de saída"/"Horímetro de
 * retorno" do checklist de mobilização/desmobilização eram só texto livre
 * (EquipmentMovementItem.value), nunca viravam um HorimeterReading real --
 * ver diagnóstico de Geradores de Energia. Cobre o caminho contrário
 * também: um item que NÃO é de horímetro não deve gerar leitura nenhuma.
 */
class EquipmentMovementHorimeterChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function makeScenario(): array
    {
        (new EquipmentMovementItemTemplateSeeder)->run();

        $plan = Plan::create([
            'name' => 'Plano Horimetro Checklist '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_equipment_movements', 'tabela_horimeter_readings'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Horimetro Checklist '.uniqid(), 'slug' => 'tenant-horimetro-check-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador 150kVA', 'status' => Asset::STATUS_DISPONIVEL,
            'horimetro_atual' => 1000,
        ]);
        $maintenanceOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Mobilização de teste', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        return [$tenant, $admin, $asset, $maintenanceOrder];
    }

    public function test_filling_the_horimeter_checklist_item_creates_a_horimeter_reading(): void
    {
        [$tenant, $admin, $asset, $maintenanceOrder] = $this->makeScenario();
        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $movement = $component->instance()->equipmentMovement;
        $item = $movement->items()->where('label', 'Horímetro de saída')->firstOrFail();

        $component->call('expand', $item->id)
            ->set('newHorimeterValue', 1250.5)
            ->call('saveItemDetails');

        $reading = HorimeterReading::where('equipment_movement_item_id', $item->id)->first();

        $this->assertNotNull($reading, 'esperava um HorimeterReading vinculado ao item de checklist');
        $this->assertSame($tenant->id, $reading->tenant_id);
        $this->assertSame($asset->id, $reading->asset_id);
        $this->assertEquals(1250.5, (float) $reading->reading);
        $this->assertSame(HorimeterReading::SOURCE_CHECKLIST, $reading->source);
        $this->assertSame($admin->id, $reading->recorded_by);

        $this->assertEquals(1250.5, (float) $asset->fresh()->horimetro_atual, 'Asset.horimetro_atual deveria sincronizar via HorimeterReadingObserver');
    }

    public function test_saving_the_same_horimeter_item_twice_updates_instead_of_duplicating(): void
    {
        [, $admin, , $maintenanceOrder] = $this->makeScenario();
        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $movement = $component->instance()->equipmentMovement;
        $item = $movement->items()->where('label', 'Horímetro de saída')->firstOrFail();

        $component->call('expand', $item->id)
            ->set('newHorimeterValue', 1200)
            ->call('saveItemDetails');

        $component->call('expand', $item->id)
            ->set('newHorimeterValue', 1210)
            ->call('saveItemDetails');

        $this->assertSame(1, HorimeterReading::where('equipment_movement_item_id', $item->id)->count());
        $this->assertEquals(1210, (float) HorimeterReading::where('equipment_movement_item_id', $item->id)->first()->reading);
    }

    public function test_horimeter_field_is_required_for_horimeter_items(): void
    {
        [, $admin, , $maintenanceOrder] = $this->makeScenario();
        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $movement = $component->instance()->equipmentMovement;
        $item = $movement->items()->where('label', 'Horímetro de saída')->firstOrFail();

        $component->call('expand', $item->id)
            ->set('newHorimeterValue', null)
            ->call('saveItemDetails')
            ->assertHasErrors(['newHorimeterValue']);

        $this->assertSame(0, HorimeterReading::where('equipment_movement_item_id', $item->id)->count());
    }

    public function test_non_horimeter_items_do_not_create_a_reading(): void
    {
        [, $admin, , $maintenanceOrder] = $this->makeScenario();
        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $movement = $component->instance()->equipmentMovement;
        $item = $movement->items()->where('label', 'Nível de óleo do motor')->firstOrFail();

        $component->call('expand', $item->id)
            ->set('newObservation', 'Nível ok')
            ->call('saveItemDetails');

        $this->assertSame(0, HorimeterReading::count());
    }
}
