<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Painel de Gestão de Clientes (2026-08-26): impressão minimalista do
 * histórico de um Client. ClientManagementPrintController (não
 * TablePrintController, que só serve 1 model+ids) agrega mensagens +
 * pendências das 3 fontes.
 */
class ClientManagementPrintTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdminAndClient(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Print '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_client_messages'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Print '.uniqid(), 'slug' => 'tenant-print-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-print-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Print',
            'email' => 'print-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        return [$tenant, $admin, $client];
    }

    public function test_print_route_renders_client_history(): void
    {
        [, $admin, $client] = $this->makeTenantAdminAndClient();

        ClientMessage::create([
            'tenant_id' => $client->tenant_id, 'client_id' => $client->id,
            'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $client->id,
            'body' => 'Mensagem para impressão.',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/gestao-clientes/'.$client->id.'/print');

        $response->assertOk();
        $response->assertSee($client->name);
        $response->assertSee('Mensagem para impressão.');
    }
}
