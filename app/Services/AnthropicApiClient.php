<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client compartilhado pra chamadas ao Claude (Anthropic Messages API) --
 * usado por todos os servicos de IA do Oravel (EquipmentDamageDiagnosisService,
 * LogisticsRouteAnalysisService, CommercialLeadAnalysisService, etc), pra
 * nao duplicar a mesma logica de request/parse em cada um.
 *
 * Bug real encontrado 2026-07-26: response.content pode vir com um bloco
 * "thinking" ANTES do bloco de texto de verdade (extended thinking do
 * modelo), entao pegar sempre content[0] falha silenciosamente quando
 * isso acontece -- content.0.text vira null. Corrigido procurando o
 * primeiro bloco com type=text, em vez de assumir indice 0.
 */
class AnthropicApiClient
{
    private const MODEL = 'claude-sonnet-5';

    private const API_URL = 'https://api.anthropic.com/v1/messages';

    /**
     * @param  array<int, array<string, mixed>>|string  $userContent
     * @return array{ok: bool, text: ?string, error: ?string}
     */
    public function send(string $systemPrompt, array|string $userContent, int $maxTokens = 1500): array
    {
        $apiKey = config('services.anthropic.key');

        if (blank($apiKey)) {
            return ['ok' => false, 'text' => null, 'error' => 'ANTHROPIC_API_KEY não configurada.'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
                ->timeout(60)
                ->post(self::API_URL, [
                    'model' => self::MODEL,
                    'max_tokens' => $maxTokens,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userContent],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao chamar Claude API', ['error' => $e->getMessage()]);

            return ['ok' => false, 'text' => null, 'error' => $e->getMessage()];
        }

        if (! $response->ok()) {
            Log::warning('Claude API retornou erro', ['status' => $response->status(), 'body' => $response->body()]);

            return [
                'ok' => false,
                'text' => null,
                'error' => "Claude API respondeu {$response->status()}: ".(string) ($response->json('error.message') ?? $response->body()),
            ];
        }

        $text = $this->extractText($response->json('content') ?? []);

        if ($text === null) {
            return ['ok' => false, 'text' => null, 'error' => 'A resposta da IA não veio em um formato reconhecível.'];
        }

        return ['ok' => true, 'text' => $text, 'error' => null];
    }

    /**
     * Extrai o JSON de dentro de um texto que pode vir com crases
     * ```json ... ``` -- o system prompt pede "sem markdown" mas o modelo
     * as vezes ignora isso mesmo assim.
     */
    public function parseJson(?string $text): ?array
    {
        if (blank($text)) {
            return null;
        }

        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);

        $decoded = json_decode(trim($text), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contentBlocks
     */
    private function extractText(array $contentBlocks): ?string
    {
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? null) === 'text' && isset($block['text'])) {
                return $block['text'];
            }
        }

        return null;
    }
}
