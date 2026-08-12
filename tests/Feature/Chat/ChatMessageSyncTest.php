<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMessage;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatMessageSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithTwoUsers(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Chat '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['modulo_chat'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Chat '.uniqid(), 'slug' => 'tenant-chat-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $userA = User::create([
            'name' => 'Usuário A', 'email' => 'usera-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha-teste-123'), 'tenant_id' => $tenant->id,
        ]);
        $userA->forceFill(['email_verified_at' => now()])->save();

        $userB = User::create([
            'name' => 'Usuário B', 'email' => 'userb-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha-teste-123'), 'tenant_id' => $tenant->id,
        ]);
        $userB->forceFill(['email_verified_at' => now()])->save();

        return [$tenant, $userA, $userB];
    }

    public function test_offline_message_is_synced_and_persisted(): void
    {
        [, $userA, $userB] = $this->makeTenantWithTwoUsers();

        $response = $this->actingAs($userA)->postJson(route('chat.messages.sync'), [
            'recipient_id' => $userB->id,
            'message' => 'Mensagem enviada offline e sincronizada depois',
            'client_id' => 'c-teste-1',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'client_id' => 'c-teste-1']);

        $this->assertDatabaseHas('chat_messages', [
            'user_id' => $userA->id,
            'message' => 'Mensagem enviada offline e sincronizada depois',
        ]);
    }

    public function test_syncing_the_same_client_id_twice_does_not_duplicate_message(): void
    {
        [, $userA, $userB] = $this->makeTenantWithTwoUsers();

        $payload = [
            'recipient_id' => $userB->id,
            'message' => 'Não deve duplicar',
            'client_id' => 'c-idempotente-1',
        ];

        $first = $this->actingAs($userA)->postJson(route('chat.messages.sync'), $payload);
        $second = $this->actingAs($userA)->postJson(route('chat.messages.sync'), $payload);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame(
            $first->json('message_id'),
            $second->json('message_id'),
            'A segunda sincronização com o mesmo client_id deveria devolver a mesma mensagem, não criar outra.'
        );

        $this->assertSame(
            1,
            ChatMessage::where('message', 'Não deve duplicar')->count()
        );
    }

    public function test_guest_cannot_sync_messages(): void
    {
        [, , $userB] = $this->makeTenantWithTwoUsers();

        $response = $this->postJson(route('chat.messages.sync'), [
            'recipient_id' => $userB->id,
            'message' => 'Não autenticado',
            'client_id' => 'c-guest-1',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Proteção cross-tenant: resolveOrCreateChatRoom() (InteractsWithChat)
     * não valida por si só o tenant do destinatário quando a sala ainda
     * não existe -- o controller precisa checar isso explicitamente antes
     * de criar/usar a sala (ver comentário em ChatMessageSyncController).
     */
    public function test_cannot_sync_message_to_user_from_another_tenant(): void
    {
        [, $userA] = $this->makeTenantWithTwoUsers();
        [, , $outsiderFromOtherTenant] = $this->makeTenantWithTwoUsers();

        $response = $this->actingAs($userA)->postJson(route('chat.messages.sync'), [
            'recipient_id' => $outsiderFromOtherTenant->id,
            'message' => 'Não deveria ser aceito',
            'client_id' => 'c-cross-tenant-1',
        ]);

        $response->assertNotFound();

        $this->assertDatabaseMissing('chat_messages', [
            'message' => 'Não deveria ser aceito',
        ]);
    }
}
