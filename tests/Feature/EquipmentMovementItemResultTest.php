<?php

namespace Tests\Feature;

use App\Livewire\EquipmentMovementMobile;
use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItem;
use App\Models\EquipmentMovementItemTemplate;
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
 * Antes desta feature, itens do checklist de mobilizacao so' tinham
 * is_checked (boolean simples), insuficiente pra uma inspecao formal
 * NR-18/NR-35 que exige resultado categorizado por item. is_checked
 * continua controlando progresso/trava de finalizacao -- result e' um
 * dado adicional.
 */
class EquipmentMovementItemResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_a_result_persists_it_and_toggling_the_same_value_clears_it(): void
    {
        (new EquipmentMovementItemTemplateSeeder)->run();

        $plan = Plan::create([
            'name' => 'Plano Result '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_equipment_movements'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Result '.uniqid(), 'slug' => 'tenant-result-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Plataforma Tesoura', 'status' => Asset::STATUS_DISPONIVEL]);
        $maintenanceOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Mobilização de teste', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $movement = $component->instance()->equipmentMovement;
        $item = $movement->items()->where('label', 'Sensor de inclinação')->firstOrFail();

        $component->call('expand', $item->id)
            ->call('setResult', EquipmentMovementItem::RESULT_NOK)
            ->call('saveItemDetails');

        $this->assertSame(EquipmentMovementItem::RESULT_NOK, $item->fresh()->result);

        // Clicar de novo no mesmo resultado desmarca (toggle)
        $component->call('expand', $item->id)
            ->call('setResult', EquipmentMovementItem::RESULT_NOK)
            ->call('saveItemDetails');

        $this->assertNull($item->fresh()->result);
    }

    public function test_invalid_result_value_is_rejected(): void
    {
        (new EquipmentMovementItemTemplateSeeder)->run();

        $plan = Plan::create([
            'name' => 'Plano Result Invalido '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_equipment_movements'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Result Invalido '.uniqid(), 'slug' => 'tenant-result-invalido-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Plataforma', 'status' => Asset::STATUS_DISPONIVEL]);
        $maintenanceOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Mobilização de teste', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $movement = $component->instance()->equipmentMovement;
        $item = $movement->items()->where('label', 'Nível de óleo do motor')->firstOrFail();

        $component->call('expand', $item->id)
            ->set('newResult', 'valor_invalido')
            ->call('saveItemDetails')
            ->assertHasErrors(['newResult']);
    }

    public function test_security_items_for_platform_are_seeded_in_both_movement_types(): void
    {
        (new EquipmentMovementItemTemplateSeeder)->run();

        $mobilizacaoLabels = EquipmentMovementItemTemplate::where('type', EquipmentMovement::TYPE_MOBILIZACAO)->pluck('label');
        $desmobilizacaoLabels = EquipmentMovementItemTemplate::where('type', EquipmentMovement::TYPE_DESMOBILIZACAO)->pluck('label');

        foreach (['Sensor de inclinação', 'Sistema de parada de emergência', 'Trava de plataforma', 'Cinto de segurança'] as $expected) {
            $this->assertTrue($mobilizacaoLabels->contains($expected), "esperava '{$expected}' nos itens de mobilização");
            $this->assertTrue($desmobilizacaoLabels->contains($expected), "esperava '{$expected}' nos itens de desmobilização");
        }
    }
}
