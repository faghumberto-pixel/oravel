<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppMessageJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Recebe eventos da WhatsApp Cloud API (Meta). Dois métodos:
 * - verify(): handshake GET exigido pela Meta ao cadastrar a URL do
 *   webhook (compara hub.verify_token com config('services.whatsapp.webhook_token')).
 * - handle(): POST com o payload de mensagem -- só extrai telefone/texto
 *   e despacha o Job, sem chamar IA nem WhatsApp aqui, pra devolver 200
 *   rápido e não estourar o timeout que a Meta aplica no webhook.
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $expectedToken = config('services.whatsapp.webhook_token');

        if ($mode === 'subscribe' && filled($expectedToken) && $token === $expectedToken) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): JsonResponse
    {
        if (! $this->hasValidSignature($request)) {
            return response()->json(['status' => 'forbidden'], 403);
        }

        $validated = $request->validate([
            'entry' => ['nullable', 'array'],
            'entry.*.changes' => ['nullable', 'array'],
            'entry.*.changes.*.value.messages' => ['nullable', 'array'],
            'entry.*.changes.*.value.messages.*.from' => ['nullable', 'string'],
            'entry.*.changes.*.value.messages.*.id' => ['nullable', 'string'],
            'entry.*.changes.*.value.messages.*.type' => ['nullable', 'string'],
            'entry.*.changes.*.value.messages.*.text.body' => ['nullable', 'string'],
        ]);

        foreach ($validated['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $message) {
                    $phone = $message['from'] ?? null;
                    $text = $message['text']['body'] ?? null;

                    // Ignora silenciosamente mensagens sem texto (imagem,
                    // áudio, figurinha, etc) -- fora de escopo deste fluxo;
                    // não é um payload inválido, só não há nada pra
                    // processar aqui ainda.
                    if (blank($phone) || blank($text)) {
                        continue;
                    }

                    ProcessWhatsAppMessageJob::dispatch($phone, $text);
                }
            }
        }

        return response()->json(['status' => 'received'], 200);
    }

    /**
     * A Meta assina o corpo bruto do POST com HMAC-SHA256 usando o App
     * Secret (não o hub_verify_token, que só serve pro handshake GET) e
     * manda o resultado no header X-Hub-Signature-256, formato
     * "sha256=<hex>". Sem isso, qualquer requisição externa podia
     * disparar ProcessWhatsAppMessageJob com phone/text arbitrários.
     */
    private function hasValidSignature(Request $request): bool
    {
        $appSecret = config('services.whatsapp.app_secret');

        if (blank($appSecret)) {
            return false;
        }

        $header = $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, substr($header, strlen('sha256=')));
    }
}
