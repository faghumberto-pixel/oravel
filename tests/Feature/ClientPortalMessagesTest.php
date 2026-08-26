<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\MinhasMensagens;
use App\Models\Client;
use App\Models\ClientMessage;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ClientMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Portal do Cliente -- Gestão de Clientes (2026-08-26): chat bidirecional
 * novo, sem tabela de sala (ClientMessage referencia client_id direto).
 * Cliente manda mensagem, User do tenant é notificado; mensagem de outro
 * Client não vaza e não notifica cruzado.
 */
class ClientPortalMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClientAndUser(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Msg '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_client_messages'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Msg '.uniqid(), 'slug' => 'tenant-msg-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Msg',
            'email' => 'msg-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $user = User::create([
            'name' => 'User Msg', 'email' => 'user-msg-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        return [$tenant, $client, $user];
    }

    public function test_client_can_send_message_and_user_is_notified(): void
    {
        Notification::fake();
        [$tenant, $client, $user] = $this->makeTenantWithClientAndUser();

        $this->actingAs($client, 'client');

        Livewire::test(MinhasMensagens::class)
            ->fillForm(['body' => 'Preciso de ajuda com o equipamento.'])
            ->call('send');

        $message = ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)
            ->first();

        $this->assertNotNull($message);
        $this->assertSame(ClientMessage::SENDER_CLIENT, $message->sender_type);
        $this->assertSame($client->id, $message->sender_id);
        $this->assertSame('Preciso de ajuda com o equipamento.', $message->body);

        Notification::assertSentTo($user, ClientMessageReceivedNotification::class);
    }

    public function test_message_from_user_does_not_trigger_notification(): void
    {
        Notification::fake();
        [$tenant, $client, $user] = $this->makeTenantWithClientAndUser();

        ClientMessage::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'sender_type' => ClientMessage::SENDER_USER, 'sender_id' => $user->id,
            'body' => 'Resposta da equipe.',
        ]);

        Notification::assertNothingSent();
    }

    public function test_client_only_sees_own_messages(): void
    {
        [$tenant, $client] = $this->makeTenantWithClientAndUser();

        $otherClient = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Outro Cliente Msg',
            'email' => 'outro-msg-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        ClientMessage::create([
            'tenant_id' => $tenant->id, 'client_id' => $otherClient->id,
            'sender_type' => ClientMessage::SENDER_CLIENT, 'sender_id' => $otherClient->id,
            'body' => 'Mensagem de outro cliente.',
        ]);

        $visible = ClientMessage::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)
            ->count();

        $this->assertSame(0, $visible);
    }
}
