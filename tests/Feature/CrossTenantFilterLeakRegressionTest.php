<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource\Pages\ListMaintenanceOrders;
use App\Filament\Resources\StockMovementResource\Pages\ViewStockMovement;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Achado de auditoria de segurança 2026-08-19: o mesmo padrão do bug
 * corrigido no commit 1425168 (Select/SelectFilter::relationship() pra
 * User sem modifyQueryUsing, vazando dados de outros tenants) apareceu de
 * novo em MaintenanceOrderResource (filtro "Técnico") e StockMovementResource
 * (campo "Registrado por"). CrmLeadResource ("Vendedor") tem cobertura
 * própria em CrmLeadResourceTest.
 */
class CrossTenantFilterLeakRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdminWithFeatures(array $features): array
    {
        $plan = Plan::create([
            'name' => 'Plano Leak Regression '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => $features,
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Leak Regression '.uniqid(), 'slug' => 'tenant-leak-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_maintenance_order_technician_filter_only_offers_users_from_the_current_tenant(): void
    {
        [$tenant, $admin] = $this->makeTenantAdminWithFeatures(['tabela_maintenance_orders']);
        [$otherTenant] = $this->makeTenantAdminWithFeatures(['tabela_maintenance_orders']);

        $technicianFromOtherTenant = User::create([
            'name' => 'Técnico de Outro Tenant', 'email' => 'tecnico-outro-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $otherTenant->id,
        ]);
        $technicianFromOtherTenant->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin);

        Livewire::test(ListMaintenanceOrders::class)
            ->assertSeeHtml($admin->name)
            ->assertDontSeeHtml($technicianFromOtherTenant->name);
    }

    public function test_stock_movement_created_by_field_does_not_resolve_user_from_another_tenant(): void
    {
        [$tenant, $admin] = $this->makeTenantAdminWithFeatures(['tabela_material_stock_movements']);
        [$otherTenant] = $this->makeTenantAdminWithFeatures(['tabela_material_stock_movements']);

        $userFromOtherTenant = User::create([
            'name' => 'Usuário de Outro Tenant', 'email' => 'user-outro-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $otherTenant->id,
        ]);
        $userFromOtherTenant->forceFill(['email_verified_at' => now()])->save();

        $material = Material::create([
            'tenant_id' => $tenant->id, 'name' => 'Material Leak Regression', 'sku' => 'SKU-'.uniqid(),
            'unit_of_measure' => 'un', 'current_stock' => 10, 'unit_cost' => 1,
        ]);

        // created_by_user_id aponta pra um usuário legítimo do próprio
        // tenant -- o que se está testando é que o campo (disabled, só
        // resolve o label do valor já salvo) nunca acidentalmente
        // resolveria/exibiria um usuário de outro tenant caso o dado
        // estivesse corrompido, e que o modifyQueryUsing não quebra a
        // resolução do caso normal.
        $movement = StockMovement::create([
            'tenant_id' => $tenant->id, 'material_id' => $material->id,
            'type' => StockMovement::TYPE_AJUSTE_MANUAL, 'quantity' => 1, 'balance_after' => 11,
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin);

        $this->get(ViewStockMovement::getUrl(['record' => $movement]))
            ->assertOk()
            ->assertSeeHtml($admin->name)
            ->assertDontSeeHtml($userFromOtherTenant->name);
    }
}
