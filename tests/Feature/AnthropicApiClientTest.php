<?php

namespace Tests\Feature;

use App\Services\AnthropicApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnthropicApiClientTest extends TestCase
{
    public function test_send_uses_a_default_max_tokens_high_enough_to_avoid_truncating_long_json(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '{"ok":true}']],
            ], 200),
        ]);

        app(AnthropicApiClient::class)->send('system', 'user');

        Http::assertSent(function ($request) {
            // Bug real 2026-07-26: default de 1500 truncava respostas longas
            // (ex: analise de estoque com varios itens) no meio do JSON,
            // fazendo parseJson() rejeitar um texto que a IA respondeu
            // corretamente ate' o limite bater. 4096 da' folga real.
            return $request['max_tokens'] === 4096;
        });
    }

    public function test_send_respects_an_explicit_max_tokens_override(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '{"ok":true}']],
            ], 200),
        ]);

        app(AnthropicApiClient::class)->send('system', 'user', 800);

        Http::assertSent(fn ($request) => $request['max_tokens'] === 800);
    }

    public function test_parse_json_rejects_truncated_json_without_throwing(): void
    {
        // Reproduz o formato exato de truncamento visto em producao: o
        // ultimo item de um array de string ficou cortado no meio, sem
        // fechar aspas/colchetes/chaves.
        $truncated = '{"resumo_geral":"...","recomendacoes_estoque_parado":["item 1","ite';

        $result = app(AnthropicApiClient::class)->parseJson($truncated);

        $this->assertNull($result);
    }

    public function test_parse_json_strips_markdown_fences_and_decodes(): void
    {
        $withFences = "```json\n".json_encode(['a' => 1])."\n```";

        $result = app(AnthropicApiClient::class)->parseJson($withFences);

        $this->assertSame(['a' => 1], $result);
    }
}
