<?php

namespace Tests\Feature;

use App\Filament\Pages\GestaoClientes;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-26 ("encaminhar entre si"): reclassificar a
 * área de uma mensagem já recebida, se foi classificada errado. Sem
 * histórico de quem reclassificou (não pedido) -- só muda a área, e o
 * filtro de visibilidade já reflete a mudança automaticamente.
 */
class ClientMessageReclassifyTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdminAndClient(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Reclassify '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_client_messages'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Reclassify '.uniqid(), 'slug' => 'tenant-reclassify-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-reclassify-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Reclassify',
            'email' => 'reclassify-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        return [$tenant, $admin, $client];
    }

    public function test_reclassify_changes_message_area(): void
    {
        [$tenant, $admin, $client] = $this->makeTenantAdminAndClient();

        $message = ClientMessage::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'area' => ClientMessage::AREA_COMERCIAL,
            'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $client->id,
            'body' => 'Na verdade é sobre pagamento.',
        ]);

        $this->actingAs($admin);

        Livewire::test(GestaoClientes::class)
            ->call('reclassify', $message->id, ClientMessage::AREA_FINANCEIRO);

        $this->assertSame(ClientMessage::AREA_FINANCEIRO, $message->fresh()->area);
    }

    public function test_visibility_reflects_new_area_after_reclassify(): void
    {
        [$tenant, , $client] = $this->makeTenantAdminAndClient();

        $comercialUser = User::create([
            'name' => 'Comercial', 'email' => 'comercial-reclassify-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $comercialRole = Role::firstOrCreate([
            'name' => ClientMessage::areaRoleName(ClientMessage::AREA_COMERCIAL),
            'guard_name' => 'web', 'tenant_id' => $tenant->id,
        ]);
        $comercialUser->assignRole($comercialRole);

        $message = ClientMessage::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'area' => ClientMessage::AREA_COMERCIAL,
            'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $client->id,
            'body' => 'Na verdade é sobre pagamento.',
        ]);

        $message->update(['area' => ClientMessage::AREA_FINANCEIRO]);

        $areas = $comercialUser->visibleClientMessageAreas();
        $visible = ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)
            ->where(fn ($q) => $q->whereIn('area', $areas)->orWhereNull('area'))
            ->count();

        $this->assertSame(0, $visible);
    }
}
