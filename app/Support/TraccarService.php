<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Unica camada de acesso ao servidor Traccar -- o tenant nunca fala com o
 * Traccar direto, so via esses metodos. Autentica com Basic Auth em cada
 * chamada (Traccar aceita isso nativamente em toda rota REST) em vez de
 * gerenciar cookie de sessao: mais simples e sem estado pra expirar.
 */
class TraccarService
{
    private string $baseUrl;

    private ?string $email;

    private ?string $password;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.traccar.url'), '/');
        $this->email = config('services.traccar.email');
        $this->password = config('services.traccar.password');
    }

    /**
     * Posicoes atuais dos devices informados. Sem TRACCAR_URL/EMAIL/PASSWORD
     * configurados, retorna vazio em vez de quebrar quem chama.
     */
    public function getPositions(array $deviceIds): array
    {
        if (blank($this->baseUrl) || blank($this->email) || $deviceIds === []) {
            return [];
        }

        $response = $this->client()
            ->get('/api/positions', ['deviceId' => $deviceIds]);

        if ($response->failed()) {
            Log::warning('TraccarService: falha ao buscar posicoes.', [
                'device_ids' => $deviceIds,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Historico de rota (lista de posicoes) de um device no periodo.
     */
    public function getRoute(int $deviceId, Carbon $from, Carbon $to): array
    {
        if (blank($this->baseUrl) || blank($this->email)) {
            return [];
        }

        $response = $this->client()
            ->get('/api/reports/route', [
                'deviceId' => $deviceId,
                'from' => $from->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
                'to' => $to->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);

        if ($response->failed()) {
            Log::warning('TraccarService: falha ao buscar rota.', [
                'device_id' => $deviceId,
                'status' => $response->status(),
            ]);

            return [];
        }

        return $response->json() ?? [];
    }

    /**
     * Distancia percorrida (km) no periodo, via /api/reports/summary do
     * Traccar (que ja calcula a distancia por dia em metros -- somamos).
     */
    public function calculateDistanceKm(int $deviceId, Carbon $from, Carbon $to): float
    {
        if (blank($this->baseUrl) || blank($this->email)) {
            return 0.0;
        }

        $response = $this->client()
            ->get('/api/reports/summary', [
                'deviceId' => $deviceId,
                'from' => $from->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
                'to' => $to->clone()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]);

        if ($response->failed()) {
            Log::warning('TraccarService: falha ao buscar resumo de distancia.', [
                'device_id' => $deviceId,
                'status' => $response->status(),
            ]);

            return 0.0;
        }

        $totalMeters = collect($response->json() ?? [])
            ->sum(fn (array $day) => $day['distance'] ?? 0);

        return round($totalMeters / 1000, 2);
    }

    private function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->email, (string) $this->password)
            ->acceptJson()
            ->timeout(15);
    }
}
