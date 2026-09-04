<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class WhatsAppService
{
    private string $provider;
    private string $apiUrl;
    private string $apiKey;
    private ?string $instance;

    public function __construct()
    {
        $this->provider = config('services.whatsapp.provider', 'evolution');
        $this->apiUrl = config('services.whatsapp.api_url', '');
        $this->apiKey = config('services.whatsapp.api_key', '');
        $this->instance = config('services.whatsapp.instance', '');
    }

    public function sendMessage(string $phone, string $message): bool
    {
        try {
            return match ($this->provider) {
                'evolution' => $this->sendViaEvolutionApi($phone, $message),
                'twilio' => $this->sendViaTwilio($phone, $message),
                'zapi' => $this->sendViaZApi($phone, $message),
                default => false,
            };
        } catch (Throwable $e) {
            \Log::error('Erro ao enviar WhatsApp', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function sendViaEvolutionApi(string $phone, string $message): bool
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->apiUrl}/message/sendText/{$this->instance}", [
                'number' => $phone,
                'text' => $message,
            ]);
        return $response->successful();
    }

    private function sendViaTwilio(string $phone, string $message): bool
    {
        $response = Http::withBasicAuth(
            config('services.whatsapp.account_sid'),
            config('services.whatsapp.auth_token')
        )->post("https://api.twilio.com/2010-04-01/Accounts/".config('services.whatsapp.account_sid')."/Messages.json", [
            'From' => config('services.whatsapp.from'),
            'To' => "whatsapp:{$phone}",
            'Body' => $message,
        ]);
        return $response->successful();
    }

    private function sendViaZApi(string $phone, string $message): bool
    {
        $response = Http::post('https://api.z-api.io/instances/me/token/send', [
            'phone' => $phone,
            'message' => $message,
        ], ['Authorization' => "Bearer {$this->apiKey}"]);
        return $response->successful();
    }

    public static function validatePhone(string $phone): bool
    {
        $phone = preg_replace('/\D/', '', $phone);
        return strlen($phone) >= 12 && strlen($phone) <= 15;
    }
}
