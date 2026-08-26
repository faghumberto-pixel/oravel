<?php

namespace Tests\Feature;

use App\Filament\Pages\GestaoClientes;
use App\Models\Asset;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ClientCommunicationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Painel de Gestão de Clientes (2026-08-26): espelho no admin do Portal
 * do Cliente -- pendências agregadas de 3 fontes, chat, comunicado em
 * massa. Filament Page customizada (não Resource), mesmo padrão de
 * MaintenanceKanban.
 */
class ClientManagementPanelTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano GestaoClientes '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_client_messages', 'tabela_contracts', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant GestaoClientes '.uniqid(), 'slug' => 'tenant-gestao-clientes-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_admin_can_reply_to_client_message(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Painel',
            'email' => 'painel-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        ClientMessage::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $client->id,
            'body' => 'Preciso de ajuda.',
        ]);

        $this->actingAs($admin);

        Livewire::test(GestaoClientes::class)
            ->call('selectClient', $client->id)
            ->fillForm(['body' => 'Já estamos verificando.'], 'replyForm')
            ->call('reply');

        $reply = ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)
            ->where('sender_type', ClientMessage::SENDER_USER)
            ->first();

        $this->assertNotNull($reply);
        $this->assertSame('Já estamos verificando.', $reply->body);
        $this->assertSame($admin->id, $reply->sender_id);
    }

    public function test_selecting_client_marks_their_messages_as_read(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Leitura',
            'email' => 'leitura-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $message = ClientMessage::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $client->id,
            'body' => 'Olá.',
        ]);

        $this->actingAs($admin);

        Livewire::test(GestaoClientes::class)->call('selectClient', $client->id);

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_pending_count_aggregates_three_sources(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Pendencias',
            'email' => 'pend-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Pendencia', 'status' => Asset::STATUS_LOCADO]);
        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-PEND-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000,
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto',
            'description' => 'Falha reportada pelo cliente.',
        ]);

        $this->actingAs($admin);

        $clients = Livewire::test(GestaoClientes::class)->get('clients');
        $target = collect($clients)->firstWhere('id', $client->id);

        $this->assertNotNull($target);
        $this->assertSame(1, $target->pending_count);
    }

    public function test_communication_sent_only_to_clients_with_portal_access(): void
    {
        Notification::fake();
        [$tenant, $admin] = $this->makeTenantAdmin();

        $withAccess = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Com Acesso',
            'email' => 'com-acesso-com-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $withoutAccess = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Sem Acesso',
            'email' => 'sem-acesso-com-'.uniqid().'@teste.com', 'password' => 'senha123',
        ]);

        $this->actingAs($admin);

        Livewire::test(GestaoClientes::class)
            ->fillForm([
                'client_ids' => [],
                'subject' => 'Aviso Importante',
                'body' => 'Manutenção programada.',
            ], 'communicationForm')
            ->call('sendCommunication');

        Notification::assertSentTo($withAccess, ClientCommunicationNotification::class);
        Notification::assertNotSentTo($withoutAccess, ClientCommunicationNotification::class);
    }
}
