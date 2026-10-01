<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Geolocalização de IP -- cidade/estado pra Acessos e Visitantes (Central),
 * pedido do usuário 2026-09-27. API gratuita (ip-api.com, sem chave, 45
 * consultas/minuto -- trocado de ipapi.co no mesmo dia, achado em PROD que
 * a cota gratuita de lá já estava esgotada, 429 "RateLimited"). Só HTTP,
 * não HTTPS, no plano gratuito -- aceitável aqui porque é uma chamada
 * servidor-servidor sem dado sensível, nunca exposta ao navegador do
 * visitante. Consultada só 1x por IP (Cache::rememberForever) -- IP muda
 * pouco pra um mesmo visitante e a localização não muda de um hit pro
 * outro. Falha de rede/rate-limit nunca pode quebrar o tracking (mesma
 * filosofia best-effort de TrackSiteVisit) -- devolve null nesses casos.
 */
class IpGeolocationService
{
    /**
     * @return array{city: ?string, state: ?string}
     */
    public function locate(?string $ip): array
    {
        $empty = ['city' => null, 'state' => null];

        if (blank($ip) || $this->isPrivateOrLocal($ip)) {
            return $empty;
        }

        $cacheKey = "ip-geo:{$ip}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}", [
                'fields' => 'status,city,region',
            ]);

            $data = $response->ok() ? $response->json() : [];

            if (($data['status'] ?? null) !== 'success') {
                // Falha/rate-limit: NUNCA cacheia pra sempre (ver bug real
                // 2026-09-27 -- um 429 momentâneo ficava preso no cache
                // permanente, escondendo até sucessos futuros pro mesmo
                // IP). Cache curto só pra não martelar a API a cada hit
                // da mesma sessão.
                Cache::put($cacheKey, $empty, now()->addMinutes(5));

                return $empty;
            }

            $result = [
                'city' => $data['city'] ?? null,
                // Limite = tamanho da coluna site_visits.state; um código
                // maior que isso nunca pode derrubar a gravação da visita.
                'state' => isset($data['region']) ? mb_substr((string) $data['region'], 0, 10) : null,
            ];

            Cache::forever($cacheKey, $result);

            return $result;
        } catch (\Throwable $e) {
            Log::warning('IpGeolocationService: falha ao geolocalizar IP.', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return $empty;
        }
    }

    private function isPrivateOrLocal(string $ip): bool
    {
        return ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
