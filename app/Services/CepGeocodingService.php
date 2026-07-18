<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CEP -> endereco via ViaCEP e endereco -> lat/lng via Nominatim (OSM).
 * Ambos gratuitos, sem chave -- mesmo padrao ja usado no mapa de ativos
 * (Leaflet + OpenStreetMap). Nominatim exige um User-Agent identificavel
 * e tem rate limit de 1 req/s, mas o uso aqui e' pontual (disparado pelo
 * usuario ao sair do campo CEP), sem risco real de estourar.
 */
class CepGeocodingService
{
    public function lookupCep(string $cep): ?array
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get("https://viacep.com.br/ws/{$cep}/json/");
        } catch (\Throwable $e) {
            Log::warning('Falha ao consultar ViaCEP', ['cep' => $cep, 'error' => $e->getMessage()]);

            return null;
        }

        // ViaCEP devolve "erro": "true" como STRING, nao boolean -- confirmado
        // batendo na API real. "true" === true e' false em PHP, entao um
        // === estrito nunca disparava pra CEP genuinamente inexistente
        // (o servico seguia adiante com endereco vazio/invalido).
        if (! $response->ok() || filter_var($response->json('erro'), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $logradouro = $response->json('logradouro');
        $bairro = $response->json('bairro');

        return [
            'address' => trim($logradouro.($bairro ? ' - '.$bairro : '')),
            'city' => $response->json('localidade'),
            'uf' => $response->json('uf'),
        ];
    }

    public function geocodeAddress(string $address): ?array
    {
        if (trim($address) === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['User-Agent' => 'Oravel/1.0 (contato@oravel.com.br)'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $address,
                    'limit' => 1,
                    'countrycodes' => 'br',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao geocodificar endereço', ['address' => $address, 'error' => $e->getMessage()]);

            return null;
        }

        $result = $response->ok() ? ($response->json()[0] ?? null) : null;
        if (! $result) {
            return null;
        }

        return [
            'latitude' => (float) $result['lat'],
            'longitude' => (float) $result['lon'],
        ];
    }
}
