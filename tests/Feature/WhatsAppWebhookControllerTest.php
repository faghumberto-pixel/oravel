<?php

namespace Tests\Feature;

use App\Jobs\ProcessWhatsAppMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WhatsAppWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'meu-app-secret-de-teste';

    private function messagePayload(string $from, string $text, string $messageId = 'wamid.abc'): array
    {
        return [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    [
                                        'from' => $from,
                                        'id' => $messageId,
                                        'type' => 'text',
                                        'text' => ['body' => $text],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function postSigned(array $payload, ?string $secret = self::APP_SECRET): TestResponse
    {
        config(['services.whatsapp.app_secret' => self::APP_SECRET]);

        $body = json_encode($payload);
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($secret !== null) {
            $headers['X-Hub-Signature-256'] = 'sha256='.hash_hmac('sha256', $body, $secret);
        }

        return $this->call('POST', '/api/webhooks/whatsapp', [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    public function test_verify_returns_challenge_when_token_matches(): void
    {
        config(['services.whatsapp.webhook_token' => 'meu-token-secreto']);

        $response = $this->get('/api/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'meu-token-secreto',
            'hub_challenge' => '123456',
        ]));

        $response->assertOk();
        $response->assertSee('123456');
    }

    public function test_verify_rejects_wrong_token(): void
    {
        config(['services.whatsapp.webhook_token' => 'meu-token-secreto']);

        $response = $this->get('/api/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'token-errado',
            'hub_challenge' => '123456',
        ]));

        $response->assertForbidden();
    }

    public function test_verify_rejects_when_webhook_token_not_configured(): void
    {
        config(['services.whatsapp.webhook_token' => null]);

        $response = $this->get('/api/webhooks/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => '',
            'hub_challenge' => '123456',
        ]));

        $response->assertForbidden();
    }

    public function test_handle_dispatches_job_for_text_message(): void
    {
        Bus::fake();

        $response = $this->postSigned($this->messagePayload('5511999998888', 'Olá, quero saber mais'));

        $response->assertOk();
        $response->assertJson(['status' => 'received']);

        Bus::assertDispatched(ProcessWhatsAppMessageJob::class, function (ProcessWhatsAppMessageJob $job) {
            return $job->phone === '5511999998888' && $job->messageText === 'Olá, quero saber mais';
        });
    }

    public function test_handle_returns_200_quickly_without_waiting_for_job(): void
    {
        // O contrato do webhook e' devolver 200 rapido -- o dispatch (nao
        // dispatchSync) e o fake do Bus garantem que nada de IO real (IA,
        // WhatsApp) acontece dentro da requisicao HTTP em si.
        Bus::fake();

        $response = $this->postSigned($this->messagePayload('5511999998888', 'Teste de latência'));

        $response->assertOk();
        Bus::assertDispatched(ProcessWhatsAppMessageJob::class);
    }

    public function test_handle_ignores_messages_without_text_body(): void
    {
        Bus::fake();

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    ['from' => '5511999998888', 'id' => 'wamid.img', 'type' => 'image'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postSigned($payload);

        $response->assertOk();
        Bus::assertNotDispatched(ProcessWhatsAppMessageJob::class);
    }

    public function test_handle_processes_multiple_messages_in_same_payload(): void
    {
        Bus::fake();

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'messages' => [
                                    ['from' => '5511111111111', 'id' => 'wamid.1', 'type' => 'text', 'text' => ['body' => 'Mensagem 1']],
                                    ['from' => '5522222222222', 'id' => 'wamid.2', 'type' => 'text', 'text' => ['body' => 'Mensagem 2']],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postSigned($payload);

        $response->assertOk();
        Bus::assertDispatchedTimes(ProcessWhatsAppMessageJob::class, 2);
    }

    public function test_handle_returns_200_for_empty_payload(): void
    {
        Bus::fake();

        $response = $this->postSigned([]);

        $response->assertOk();
        Bus::assertNotDispatched(ProcessWhatsAppMessageJob::class);
    }

    public function test_handle_rejects_request_without_signature(): void
    {
        Bus::fake();

        $response = $this->postSigned($this->messagePayload('5511999998888', 'Sem assinatura'), secret: null);

        $response->assertForbidden();
        Bus::assertNotDispatched(ProcessWhatsAppMessageJob::class);
    }

    public function test_handle_rejects_request_with_wrong_signature(): void
    {
        Bus::fake();

        $response = $this->postSigned($this->messagePayload('5511999998888', 'Assinatura errada'), secret: 'segredo-errado');

        $response->assertForbidden();
        Bus::assertNotDispatched(ProcessWhatsAppMessageJob::class);
    }

    public function test_handle_rejects_request_when_app_secret_not_configured(): void
    {
        Bus::fake();
        config(['services.whatsapp.app_secret' => null]);

        $response = $this->postJson('/api/webhooks/whatsapp', $this->messagePayload('5511999998888', 'Sem app secret'));

        $response->assertForbidden();
        Bus::assertNotDispatched(ProcessWhatsAppMessageJob::class);
    }
}
