<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\WhatsAppChatResource\Pages\ListWhatsAppChats;
use App\Filament\Central\Resources\WhatsAppChatResource\Pages\ViewWhatsAppChat;
use App\Models\User;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WhatsAppChatResourceTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        return $super;
    }

    private function actingAsCentral(): void
    {
        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    public function test_lists_chats_and_defaults_to_human_handling_filter(): void
    {
        $this->actingAsCentral();

        $aiChat = WhatsAppChat::create(['phone_number' => '5511111111111', 'status' => WhatsAppChat::STATUS_AI_HANDLING]);
        $humanChat = WhatsAppChat::create(['phone_number' => '5522222222222', 'status' => WhatsAppChat::STATUS_HUMAN_HANDLING]);

        Livewire::test(ListWhatsAppChats::class)
            ->assertCanSeeTableRecords([$humanChat])
            ->assertCanNotSeeTableRecords([$aiChat]);
    }

    public function test_status_filter_can_be_cleared_to_see_all_chats(): void
    {
        $this->actingAsCentral();

        $aiChat = WhatsAppChat::create(['phone_number' => '5511111111111', 'status' => WhatsAppChat::STATUS_AI_HANDLING]);
        $humanChat = WhatsAppChat::create(['phone_number' => '5522222222222', 'status' => WhatsAppChat::STATUS_HUMAN_HANDLING]);

        Livewire::test(ListWhatsAppChats::class)
            ->filterTable('status', null)
            ->assertCanSeeTableRecords([$aiChat, $humanChat]);
    }

    public function test_view_page_shows_full_conversation_in_order(): void
    {
        $this->actingAsCentral();

        $chat = WhatsAppChat::create(['phone_number' => '5511999998888', 'status' => WhatsAppChat::STATUS_HUMAN_HANDLING]);
        WhatsAppMessage::create(['whatsapp_chat_id' => $chat->id, 'role' => WhatsAppMessage::ROLE_USER, 'content' => 'Primeira']);
        WhatsAppMessage::create(['whatsapp_chat_id' => $chat->id, 'role' => WhatsAppMessage::ROLE_ASSISTANT, 'content' => 'Segunda']);

        Livewire::test(ViewWhatsAppChat::class, ['record' => $chat->getKey()])
            ->assertOk()
            ->assertSee('Primeira')
            ->assertSee('Segunda');
    }

    public function test_sending_reply_sends_via_whatsapp_service_and_stores_assistant_message(): void
    {
        $this->actingAsCentral();

        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $chat = WhatsAppChat::create(['phone_number' => '5511999998888', 'status' => WhatsAppChat::STATUS_HUMAN_HANDLING]);

        Livewire::test(ViewWhatsAppChat::class, ['record' => $chat->getKey()])
            ->set('draftMessage', 'Olá, tudo bem?')
            ->call('sendReply')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_chat_id' => $chat->id,
            'role' => WhatsAppMessage::ROLE_ASSISTANT,
            'content' => 'Olá, tudo bem?',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com')
            && $request['text']['body'] === 'Olá, tudo bem?');
    }

    public function test_cannot_send_reply_when_chat_is_closed(): void
    {
        $this->actingAsCentral();

        Http::fake();

        $chat = WhatsAppChat::create(['phone_number' => '5511999998888', 'status' => WhatsAppChat::STATUS_CLOSED]);

        Livewire::test(ViewWhatsAppChat::class, ['record' => $chat->getKey()])
            ->set('draftMessage', 'Não deveria enviar')
            ->call('sendReply');

        $this->assertDatabaseMissing('whatsapp_messages', ['whatsapp_chat_id' => $chat->id]);
        Http::assertNothingSent();
    }

    public function test_return_to_ai_action_switches_status_back(): void
    {
        $this->actingAsCentral();

        $chat = WhatsAppChat::create(['phone_number' => '5511999998888', 'status' => WhatsAppChat::STATUS_HUMAN_HANDLING]);

        Livewire::test(ViewWhatsAppChat::class, ['record' => $chat->getKey()])
            ->callAction('return_to_ai')
            ->assertHasNoErrors();

        $this->assertSame(WhatsAppChat::STATUS_AI_HANDLING, $chat->fresh()->status);
    }

    public function test_close_chat_action_marks_chat_as_closed(): void
    {
        $this->actingAsCentral();

        $chat = WhatsAppChat::create(['phone_number' => '5511999998888', 'status' => WhatsAppChat::STATUS_HUMAN_HANDLING]);

        Livewire::test(ViewWhatsAppChat::class, ['record' => $chat->getKey()])
            ->callAction('close_chat')
            ->assertHasNoErrors();

        $this->assertSame(WhatsAppChat::STATUS_CLOSED, $chat->fresh()->status);
    }

    public function test_view_page_resolves_via_real_http_request(): void
    {
        $this->actingAsCentral();

        $chat = WhatsAppChat::create(['phone_number' => '5511999998888', 'contact_name' => 'Cliente Teste', 'status' => WhatsAppChat::STATUS_HUMAN_HANDLING]);

        $response = $this->get(ViewWhatsAppChat::getUrl(['record' => $chat->getKey()]));

        $response->assertOk();
        $response->assertSee('Cliente Teste');
    }

    public function test_reply_fails_gracefully_when_whatsapp_service_errors(): void
    {
        $this->actingAsCentral();

        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 401),
        ]);

        $chat = WhatsAppChat::create(['phone_number' => '5511999998888', 'status' => WhatsAppChat::STATUS_HUMAN_HANDLING]);

        Livewire::test(ViewWhatsAppChat::class, ['record' => $chat->getKey()])
            ->set('draftMessage', 'Vai falhar')
            ->call('sendReply');

        $this->assertDatabaseMissing('whatsapp_messages', ['whatsapp_chat_id' => $chat->id]);
    }
}
