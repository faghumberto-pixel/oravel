<?php

namespace App\Domain\Intelligence\Trial\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Verificação do Cloudflare Turnstile no servidor. Só exige quando a chave secreta está configurada (services.turnstile.secret):
 * sem ela (dev, testes) o cadastro segue protegido pelo que já existia (honeypot, limite e origem). Com ela, falha de rede = recusa.
 */
class Turnstile
{
    private const URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function ativo(): bool
    {
        return filled(config('services.turnstile.secret'));
    }

    public function verificar(?string $token, ?string $ip): bool
    {
        if (! self::ativo()) {
            return true;
        }
        if (blank($token)) {
            return false;
        }

        try {
            $r = Http::asForm()->timeout(6)->post(self::URL, array_filter([
                'secret' => config('services.turnstile.secret'), 'response' => $token, 'remoteip' => $ip,
            ]));

            return $r->ok() && $r->json('success') === true;
        } catch (Throwable $e) {
            Log::warning('Intelligence trial: Turnstile indisponível, cadastro recusado.', ['erro' => $e->getMessage()]);

            return false;
        }
    }
}
