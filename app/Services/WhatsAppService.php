<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client de envio pra WhatsApp Cloud API (Meta) -- credenciais em
 * config('services.whatsapp'), preenchidas via .env
 * (WHATSAPP_API_TOKEN/WHATSAPP_PHONE_NUMBER_ID). Mesmo contrato de
 * retorno ok/error usado por AnthropicApiClient, pra quem consome não
 * precisar tratar exception em cada chamada.
 */
class WhatsAppService
{
    /**
     * @return array{ok: bool, error: ?string}
     */
    public function sendMessage(string $to, string $text): array
    {
        $token = config('services.whatsapp.token');
        $phoneNumberId = config('services.whatsapp.phone_number_id');
        $baseUrl = config('services.whatsapp.base_url');

        if (blank($token) || blank($phoneNumberId)) {
            Log::warning('WhatsAppService: token ou phone_number_id não configurado, mensagem não enviada.', ['to' => $to]);

            return ['ok' => false, 'error' => 'Credenciais do WhatsApp não configuradas.'];
        }

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->post("{$baseUrl}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsAppService: falha ao chamar a API do WhatsApp', ['error' => $e->getMessage(), 'to' => $to]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if (! $response->ok()) {
            Log::warning('WhatsAppService: API do WhatsApp retornou erro', [
                'status' => $response->status(),
                'body' => $response->body(),
                'to' => $to,
            ]);

            return [
                'ok' => false,
                'error' => "WhatsApp API respondeu {$response->status()}: ".(string) ($response->json('error.message') ?? $response->body()),
            ];
        }

        return ['ok' => true, 'error' => null];
    }
}
