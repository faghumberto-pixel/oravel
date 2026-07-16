<?php

namespace Tests\Feature;

use App\Filament\Pages\AvariasReincidencia;
use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderMaterial;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvariasReincidenciaTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Avarias '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_equipment_damages', 'tabela_maintenance_orders', 'tabela_materials'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Avarias '.uniqid(), 'slug' => 'tenant-avarias-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeDamage(Tenant $tenant, Asset $asset, User $technician, string $type, ?Material $material = null): EquipmentDamage
    {
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $technician->id,
            'description' => 'Atendimento', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        if ($material) {
            MaintenanceOrderMaterial::create([
                'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id,
                'material_id' => $material->id, 'name' => $material->name, 'quantity' => 1,
            ]);
        }

        $movement = EquipmentMovement::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $order->id,
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO,
        ]);

        return EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'equipment_movement_id' => $movement->id,
            'maintenance_order_id' => $order->id, 'asset_id' => $asset->id,
            'reported_by_user_id' => $technician->id, 'severity' => EquipmentDamage::SEVERITY_MODERADA,
            'damage_type' => $type, 'description' => 'Avaria de teste',
        ]);
    }

    public function test_asset_with_two_damages_of_same_type_shows_up_as_reincidencia(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Reincidente', 'status' => 'disponivel']);
        $material = Material::create(['tenant_id' => $tenant->id, 'sku' => 'MAT-'.uniqid(), 'name' => 'Mangueira Hidráulica', 'unit_cost' => 10]);

        $this->makeDamage($tenant, $asset, $admin, EquipmentDamage::DAMAGE_TYPE_HIDRAULICO, $material);
        $this->makeDamage($tenant, $asset, $admin, EquipmentDamage::DAMAGE_TYPE_HIDRAULICO, $material);

        $this->actingAs($admin);

        $response = $this->get(AvariasReincidencia::getUrl());

        $response->assertOk();
        $response->assertSee('Gerador Reincidente');
        $response->assertSee('Hidráulico');
        $response->assertSee('2 ocorrências');
        $response->assertSee('Mangueira Hidráulica');
    }

    public function test_asset_with_damages_of_different_types_is_not_reincidencia(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Compressor Misto', 'status' => 'disponivel']);

        $this->makeDamage($tenant, $asset, $admin, EquipmentDamage::DAMAGE_TYPE_HIDRAULICO);
        $this->makeDamage($tenant, $asset, $admin, EquipmentDamage::DAMAGE_TYPE_ELETRICO);

        $this->actingAs($admin);

        $response = $this->get(AvariasReincidencia::getUrl());

        $response->assertOk();
        $response->assertDontSee('Compressor Misto');
    }

    public function test_report_does_not_leak_another_tenants_reincidencia(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = Asset::create(['tenant_id' => $tenantA->id, 'name' => 'Ativo Tenant A', 'status' => 'disponivel']);
        $this->makeDamage($tenantA, $assetA, $adminA, EquipmentDamage::DAMAGE_TYPE_MOTOR);
        $this->makeDamage($tenantA, $assetA, $adminA, EquipmentDamage::DAMAGE_TYPE_MOTOR);

        [$tenantB, $adminB] = $this->makeTenantAdmin();

        $this->actingAs($adminB);

        $response = $this->get(AvariasReincidencia::getUrl());

        $response->assertOk();
        $response->assertDontSee('Ativo Tenant A');
    }
}
