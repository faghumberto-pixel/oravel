<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppMessageJob;
use App\Models\WhatsAppChat;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessWhatsAppMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private function fakeClaudeTextResponse(string $text): void
    {
        // token/phone_number_id do WhatsApp ficam vazios por padrão no
        // ambiente de teste (nunca setados em phpunit.xml/.env.testing) --
        // sem eles, WhatsAppService::sendMessage() retorna cedo (blank())
        // e a chamada HTTP nem acontece, então Http::assertSent() nunca
        // vê a requisição pro graph.facebook.com.
        config([
            'services.whatsapp.token' => 'test-token',
            'services.whatsapp.phone_number_id' => '1234567890',
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => $text],
                ],
            ], 200),
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);
    }

    public function test_creates_chat_and_stores_user_message(): void
    {
        $this->fakeClaudeTextResponse('Olá! Como posso ajudar?');

        ProcessWhatsAppMessageJob::dispatchSync('5511999998888', 'Oi, quero saber sobre o sistema');

        $chat = WhatsAppChat::where('phone_number', '5511999998888')->first();

        $this->assertNotNull($chat);
        $this->assertSame(WhatsAppChat::STATUS_AI_HANDLING, $chat->status);
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_chat_id' => $chat->id,
            'role' => WhatsAppMessage::ROLE_USER,
            'content' => 'Oi, quero saber sobre o sistema',
        ]);
    }

    public function test_stores_assistant_reply_and_sends_via_whatsapp_service(): void
    {
        $this->fakeClaudeTextResponse('Claro, a Oravel é um ERP para locadoras.');

        ProcessWhatsAppMessageJob::dispatchSync('5511999998888', 'O que é a Oravel?');

        $chat = WhatsAppChat::where('phone_number', '5511999998888')->first();

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_chat_id' => $chat->id,
            'role' => WhatsAppMessage::ROLE_ASSISTANT,
            'content' => 'Claro, a Oravel é um ERP para locadoras.',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com')
                && $request['to'] === '5511999998888'
                && $request['text']['body'] === 'Claro, a Oravel é um ERP para locadoras.';
        });
    }

    public function test_reuses_existing_chat_and_builds_history_from_previous_messages(): void
    {
        $chat = WhatsAppChat::create(['phone_number' => '5511999998888', 'status' => WhatsAppChat::STATUS_AI_HANDLING]);
        WhatsAppMessage::create(['whatsapp_chat_id' => $chat->id, 'role' => WhatsAppMessage::ROLE_USER, 'content' => 'Primeira mensagem']);
        WhatsAppMessage::create(['whatsapp_chat_id' => $chat->id, 'role' => WhatsAppMessage::ROLE_ASSISTANT, 'content' => 'Primeira resposta']);

        $this->fakeClaudeTextResponse('Segunda resposta');

        ProcessWhatsAppMessageJob::dispatchSync('5511999998888', 'Segunda mensagem');

        $this->assertSame(1, WhatsAppChat::where('phone_number', '5511999998888')->count());
        $this->assertSame(4, WhatsAppMessage::where('whatsapp_chat_id', $chat->id)->count());

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'api.anthropic.com')) {
                return true;
            }

            $messages = $request['messages'];

            return count($messages) === 3
                && $messages[0]['content'] === 'Primeira mensagem'
                && $messages[1]['content'] === 'Primeira resposta'
                && $messages[2]['content'] === 'Segunda mensagem';
        });
    }

    public function test_does_not_respond_when_chat_is_human_handling(): void
    {
        $chat = WhatsAppChat::create(['phone_number' => '5511999998888', 'status' => WhatsAppChat::STATUS_HUMAN_HANDLING]);

        Http::fake();

        ProcessWhatsAppMessageJob::dispatchSync('5511999998888', 'Ainda estou esperando resposta');

        Http::assertNothingSent();

        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_chat_id' => $chat->id,
            'role' => WhatsAppMessage::ROLE_USER,
            'content' => 'Ainda estou esperando resposta',
        ]);
        $this->assertSame(1, WhatsAppMessage::where('whatsapp_chat_id', $chat->id)->count());
    }

    public function test_handover_tag_switches_chat_to_human_handling_and_strips_tag_from_reply(): void
    {
        $this->fakeClaudeTextResponse('Vou te transferir para um especialista. [HANDOVER]');

        ProcessWhatsAppMessageJob::dispatchSync('5511999998888', 'Quero negociar um desconto');

        $chat = WhatsAppChat::where('phone_number', '5511999998888')->first();

        $this->assertSame(WhatsAppChat::STATUS_HUMAN_HANDLING, $chat->status);
        $this->assertDatabaseHas('whatsapp_messages', [
            'whatsapp_chat_id' => $chat->id,
            'role' => WhatsAppMessage::ROLE_ASSISTANT,
            'content' => 'Vou te transferir para um especialista.',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com')
                && $request['text']['body'] === 'Vou te transferir para um especialista.';
        });
    }

    public function test_does_nothing_further_when_anthropic_key_is_missing(): void
    {
        config(['services.anthropic.key' => null]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => []], 200),
        ]);

        ProcessWhatsAppMessageJob::dispatchSync('5511999998888', 'Mensagem sem IA configurada');

        $chat = WhatsAppChat::where('phone_number', '5511999998888')->first();

        $this->assertSame(WhatsAppChat::STATUS_AI_HANDLING, $chat->status);
        $this->assertSame(1, WhatsAppMessage::where('whatsapp_chat_id', $chat->id)->count());

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com'));
    }

    public function test_limits_history_to_last_ten_messages(): void
    {
        $chat = WhatsAppChat::create(['phone_number' => '5511999998888', 'status' => WhatsAppChat::STATUS_AI_HANDLING]);

        for ($i = 1; $i <= 12; $i++) {
            WhatsAppMessage::create([
                'whatsapp_chat_id' => $chat->id,
                'role' => $i % 2 === 0 ? WhatsAppMessage::ROLE_ASSISTANT : WhatsAppMessage::ROLE_USER,
                'content' => "Mensagem {$i}",
            ]);
        }

        $this->fakeClaudeTextResponse('Resposta final');

        ProcessWhatsAppMessageJob::dispatchSync('5511999998888', 'Mensagem 13');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'api.anthropic.com')) {
                return true;
            }

            // Limite de 10 do histórico anterior + a mensagem recém-criada = 10
            return count($request['messages']) === 10
                && $request['messages'][0]['content'] === 'Mensagem 4'
                && $request['messages'][9]['content'] === 'Mensagem 13';
        });
    }
}
