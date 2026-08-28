<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real reportado pelo usuário 2026-08-28: clicar em "Imprimir OS"
 * (botão novo no Gantt de Alocação de Técnicos) dava 403 pra qualquer
 * usuário, inclusive super admin atuando num tenant de teste. Causa:
 * Filament::getTenant() sempre retorna null no painel admin (não usa
 * tenancy nativa do Filament) -- mesmo bug já corrigido antes em
 * ClientManagementPrintController. routes/web.php agora usa
 * App\Support\Tenancy::current().
 */
class MaintenanceOrderPrintRouteTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Print Route '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Print Route '.uniqid(), 'slug' => 'tenant-print-route-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Print Route', 'email' => 'admin-print-route-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_print_route_returns_ok_for_tenant_admin(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Print Route', 'patrimonio' => 'PAT-ROUTE', 'status' => Asset::STATUS_DISPONIVEL]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva print route',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->actingAs($admin)
            ->get(route('maintenance-orders.print', $order->id))
            ->assertOk();
    }

    public function test_print_route_does_not_leak_order_from_another_tenant(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        [$tenantB] = $this->makeTenantAdmin();

        $assetB = Asset::create(['tenant_id' => $tenantB->id, 'name' => 'Ativo Tenant B', 'patrimonio' => 'PAT-B', 'status' => Asset::STATUS_DISPONIVEL]);
        $orderB = MaintenanceOrder::create([
            'tenant_id' => $tenantB->id, 'asset_id' => $assetB->id, 'description' => 'Corretiva tenant B',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->actingAs($adminA)
            ->get(route('maintenance-orders.print', $orderB->id))
            ->assertNotFound();
    }
}
