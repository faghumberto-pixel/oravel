<?php

namespace Tests\Feature;

use App\Services\AnthropicApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Resposta da IA cortada pelo limite de tamanho (max_tokens) tenta de novo uma vez com mais espaço, em vez de falhar a análise. */
class AnaliseRespostaCortadaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'services.anthropic.key' => 'chave-de-teste']);
        app('db')->purge();
    }

    /** @return array<string, mixed> */
    private function corpo(string $texto, string $parada): array
    {
        return ['content' => [['type' => 'text', 'text' => $texto]], 'stop_reason' => $parada, 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]];
    }

    public function test_resposta_cortada_tenta_de_novo_com_mais_espaco_e_conclui(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::sequence()->push($this->corpo('{"resumo": "texto cortado no mei', 'max_tokens'))->push($this->corpo('{"resumo": "completo"}', 'end_turn'))]);

        $r = app(AnthropicApiClient::class)->sendJson('sistema', 'dados', 8000);

        $this->assertTrue($r['ok']);
        $this->assertSame('completo', $r['data']['resumo']);
        $tokens = [];
        Http::assertSentCount(2);
        Http::assertSent(function ($req) use (&$tokens) {
            $tokens[] = $req['max_tokens'];

            return true;
        });
        $this->assertSame([8000, 16000], $tokens);
    }

    public function test_resposta_cortada_duas_vezes_falha_com_mensagem_clara(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->corpo('{"resumo": "cortado', 'max_tokens'))]);

        $r = app(AnthropicApiClient::class)->sendJson('sistema', 'dados', 8000);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('cortada por tamanho', $r['error']);
        Http::assertSentCount(2);
    }

    public function test_texto_invalido_sem_corte_nao_gasta_segunda_chamada(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->corpo('isto nao e json', 'end_turn'))]);

        $r = app(AnthropicApiClient::class)->sendJson('sistema', 'dados', 8000);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('formato reconhecível', $r['error']);
        Http::assertSentCount(1);
    }
}
