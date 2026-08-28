<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\TechnicianAllocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-28: botão "Imprimir" no Gantt de Alocação,
 * estilo PHP minimalista igual à OS, refletindo os filtros escolhidos
 * (período, cliente, técnico, patrimônio) com resumo por técnico no topo
 * e total consolidado no rodapé.
 */
class AlocacaoTecnicosPmpPrintRouteTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Alocacao Print '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Alocacao Print '.uniqid(), 'slug' => 'tenant-alocacao-print-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Alocacao Print', 'email' => 'admin-alocacao-print-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_print_route_returns_ok_with_summary_and_totals(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Print']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Print', 'patrimonio' => 'PAT-PRINT', 'status' => Asset::STATUS_DISPONIVEL, 'client_id' => $client->id]);
        $technician = User::create([
            'name' => 'Tecnico Print', 'email' => 'tec-print-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva print',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);
        TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id, 'maintenance_order_id' => $order->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('alocacao-tecnicos-pmp.print', ['filterClientId' => $client->id]))
            ->assertOk();

        $response->assertSee('ALOCAÇÃO DE TÉCNICOS');
        $response->assertSee('Resumo por Técnico');
        $response->assertSee('Tecnico Print');
        $response->assertSee('Cliente Print');
        $response->assertSee('Totais do Período');
    }

    public function test_print_route_respects_technician_filter(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Print Filtro', 'status' => Asset::STATUS_DISPONIVEL]);
        $technicianA = User::create([
            'name' => 'Tecnico Filtro A', 'email' => 'tec-print-a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $technicianB = User::create([
            'name' => 'Tecnico Filtro B', 'email' => 'tec-print-b-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva filtro',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);
        TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technicianA->id, 'maintenance_order_id' => $order->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);

        $response = $this->actingAs($admin)
            ->get(route('alocacao-tecnicos-pmp.print', ['filterTechnicianId' => $technicianB->id]))
            ->assertOk();

        $response->assertDontSee('Tecnico Filtro A');
    }
}
