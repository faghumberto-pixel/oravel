<?php

namespace Tests\Feature;

use App\Livewire\GlobalChat;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class GlobalChatTest extends TestCase
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

        $admin = User::create([
            'name' => 'Admin Chat', 'email' => 'admin-chat-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        $colleague = User::create([
            'name' => 'Colega Chat', 'email' => 'colega-chat-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $colleague->forceFill(['email_verified_at' => now()])->save();
        $colleague->assignRole(Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin, $colleague];
    }

    public function test_tenant_scoped_user_sees_seeded_colleagues_as_contacts(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();

        $this->actingAs($admin);

        $contacts = Livewire::test(GlobalChat::class)->instance()->users();

        $this->assertCount(1, $contacts);
        $this->assertSame($colleague->id, $contacts->first()['id']);
    }

    /**
     * Regressao: wire:click="selectUser({{ id }})" sem aspas quebrava o
     * clique pra qualquer UUID (hifen nao e' token JS valido) -- o clique
     * no contato simplesmente nao fazia nada no navegador. So aparece no
     * HTML renderizado, nao numa chamada direta a selectUser() via teste.
     */
    public function test_contact_click_handler_quotes_the_uuid_in_rendered_html(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();

        $this->actingAs($admin);

        Livewire::test(GlobalChat::class)
            ->assertSeeHtml("wire:click=\"selectUser('{$colleague->id}')\"");
    }

    public function test_super_admin_without_acting_tenant_sees_no_contacts(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();

        config(['oravel.super_admins' => ['super@oravel.com.br']]);
        $super = User::create([
            'name' => 'Super', 'email' => 'super@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($super);

        $contacts = Livewire::test(GlobalChat::class)->instance()->users();

        $this->assertCount(0, $contacts);
    }

    /**
     * Achado de auditoria de segurança 2026-08-19: selectedUserId e' uma
     * propriedade publica Livewire com #[Url] (bindavel via querystring,
     * ver GlobalChat::$selectedUserId), lida diretamente por chatRoom()
     * sem validar tenant. Cobre tanto o caminho via selectUser() quanto
     * via mount() (querystring), garantindo que resolveOrCreateChatRoom()
     * bloqueia usuario de outro tenant em vez de criar uma ChatRoom
     * cruzando os dois tenants.
     */
    public function test_cannot_open_chat_room_with_user_from_another_tenant_via_select_user(): void
    {
        [, $admin] = $this->makeTenantWithTwoUsers();
        [, , $outsiderFromOtherTenant] = $this->makeTenantWithTwoUsers();

        $this->actingAs($admin);

        try {
            Livewire::test(GlobalChat::class)
                ->call('selectUser', $outsiderFromOtherTenant->id)
                ->instance()
                ->chatRoom();
            $this->fail('Esperava HttpException 403 ao tentar abrir sala com usuário de outro tenant.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_cannot_open_chat_room_with_user_from_another_tenant_via_querystring(): void
    {
        [, $admin] = $this->makeTenantWithTwoUsers();
        [, , $outsiderFromOtherTenant] = $this->makeTenantWithTwoUsers();

        $this->actingAs($admin);

        try {
            Livewire::test(GlobalChat::class, ['selectedUserId' => $outsiderFromOtherTenant->id])
                ->instance()
                ->chatRoom();
            $this->fail('Esperava HttpException 403 ao tentar abrir sala com usuário de outro tenant.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_no_chat_room_is_created_when_selected_user_belongs_to_another_tenant(): void
    {
        [, $admin] = $this->makeTenantWithTwoUsers();
        [, , $outsiderFromOtherTenant] = $this->makeTenantWithTwoUsers();

        $this->actingAs($admin);

        try {
            Livewire::test(GlobalChat::class)
                ->call('selectUser', $outsiderFromOtherTenant->id)
                ->instance()
                ->chatRoom();
        } catch (HttpException) {
            // esperado -- o que importa aqui e' o efeito colateral abaixo.
        }

        $this->assertFalse(
            ChatRoom::query()->whereHas('users', fn ($q) => $q->where('users.id', $outsiderFromOtherTenant->id))->exists(),
            'Nenhuma ChatRoom deveria ter sido criada com um usuário de outro tenant.'
        );
    }

    public function test_message_lifecycle_goes_from_sent_to_delivered_to_read(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();

        $this->actingAs($admin);
        $component = Livewire::test(GlobalChat::class)
            ->call('selectUser', $colleague->id)
            ->set('newMessage', 'Oi, tudo bem?')
            ->call('sendMessage');

        $message = ChatMessage::first();
        $this->assertNotNull($message);
        $this->assertNull($message->delivered_at);
        $this->assertNull($message->read_at);

        // Colega faz poll (lista de contatos), sem abrir a conversa do admin
        // ainda -> mensagem fica "entregue" mas nao "lida". selectedUserId
        // bogus evita o auto-select do mount() marcar como lida direto.
        $this->actingAs($colleague);
        Livewire::test(GlobalChat::class, ['selectedUserId' => (string) Str::uuid()])
            ->instance()
            ->users();

        $message->refresh();
        $this->assertNotNull($message->delivered_at);
        $this->assertNull($message->read_at);

        // Colega abre a conversa -> mensagem fica "lida"
        Livewire::test(GlobalChat::class)->call('selectUser', $admin->id);

        $message->refresh();
        $this->assertNotNull($message->read_at);
    }

    public function test_document_attachment_is_categorized_separately_from_audio_and_images(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();

        $this->actingAs($admin);

        $file = UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf');

        Livewire::test(GlobalChat::class)
            ->call('selectUser', $colleague->id)
            ->set('temporaryDocument', $file);

        $message = ChatMessage::first();
        $media = $message->getMedia('chat_attachments');

        $this->assertCount(1, $media);
        $this->assertFalse(str_starts_with($media->first()->mime_type, 'image/'));
        $this->assertFalse(str_starts_with($media->first()->mime_type, 'audio/'));

        $mapped = Livewire::test(GlobalChat::class)
            ->call('selectUser', $colleague->id)
            ->instance()
            ->chatMessages()
            ->first();

        $this->assertCount(1, $mapped['documents']);
        $this->assertEmpty($mapped['attachments']);
        $this->assertNull($mapped['audio']);
    }

    public function test_sending_audio_stores_client_side_transcript(): void
    {
        // Transcrição agora vem pronta do client-side (Web Speech API
        // nativa do navegador, ver chatComponent() em global-chat.blade.php)
        // -- não depende mais de nenhum job/serviço em background
        // (App\Jobs\TranscribeChatAudio + App\Services\AudioTranscriptionService,
        // que usavam OpenAI Whisper via OPENAI_API_KEY, removidos: a chave
        // nunca foi configurada em PROD, então a transcrição do chat nunca
        // funcionou, silenciosamente).
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();

        $this->actingAs($admin);

        $base64 = 'data:audio/webm;base64,'.base64_encode('fake-audio-bytes');

        Livewire::test(GlobalChat::class)
            ->call('selectUser', $colleague->id)
            ->call('sendAudioMessage', $base64, 'texto transcrito pelo navegador');

        $this->assertDatabaseHas('chat_messages', [
            'transcript' => 'texto transcrito pelo navegador',
        ]);
    }

    public function test_sending_audio_without_transcript_stores_null(): void
    {
        // Navegador sem suporte a Web Speech API (ex: Firefox) -- o áudio
        // continua funcionando normalmente, só sem transcrição.
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();

        $this->actingAs($admin);

        $base64 = 'data:audio/webm;base64,'.base64_encode('fake-audio-bytes');

        Livewire::test(GlobalChat::class)
            ->call('selectUser', $colleague->id)
            ->call('sendAudioMessage', $base64);

        $message = ChatMessage::latest('created_at')->first();
        $this->assertNotNull($message);
        $this->assertNull($message->transcript);
    }

    public function test_only_room_participants_can_export_pdf(): void
    {
        [$tenant, $admin, $colleague] = $this->makeTenantWithTwoUsers();

        $this->actingAs($admin);
        Livewire::test(GlobalChat::class)->call('selectUser', $colleague->id);

        $room = ChatRoom::first();
        $this->assertNotNull($room);

        $this->get(route('chat.history.pdf', ['room' => $room->id]))->assertOk();

        $outsider = User::create([
            'name' => 'Outsider', 'email' => 'outsider-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $outsider->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($outsider);
        $this->get(route('chat.history.pdf', ['room' => $room->id]))->assertForbidden();
    }
}
