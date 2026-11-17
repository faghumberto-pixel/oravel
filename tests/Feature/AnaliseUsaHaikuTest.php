<?php

namespace Tests\Feature;

use App\Services\AnthropicApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Todas as análises de IA (send) usam o Haiku por padrão; o modelo pode ser trocado por configuração. */
class AnaliseUsaHaikuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'services.anthropic.key' => 'chave-de-teste']);
        app('db')->purge();
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => '{"ok":true}']], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]])]);
    }

    private function modeloEnviado(): string
    {
        $enviado = null;
        Http::assertSent(function ($request) use (&$enviado) {
            $enviado = $request['model'];

            return true;
        });

        return (string) $enviado;
    }

    public function test_analise_usa_haiku_por_padrao(): void
    {
        $this->assertSame('claude-haiku-5-5', config('services.anthropic.analysis_model'));
        $this->assertTrue(app(AnthropicApiClient::class)->send('sistema', 'dados')['ok']);
        $this->assertSame('claude-haiku-5-5', $this->modeloEnviado());
    }

    public function test_modelo_da_analise_pode_ser_trocado_por_configuracao(): void
    {
        config(['services.anthropic.analysis_model' => 'claude-sonnet-5']);
        app(AnthropicApiClient::class)->send('sistema', 'dados');
        $this->assertSame('claude-sonnet-5', $this->modeloEnviado());
    }

    public function test_conversa_sem_modelo_explicito_tambem_usa_haiku(): void
    {
        app(AnthropicApiClient::class)->sendConversation('sistema', [['role' => 'user', 'content' => 'oi']]);
        $this->assertSame('claude-haiku-5-5', $this->modeloEnviado());
    }

    public function test_conversa_com_modelo_explicito_respeita_o_modelo(): void
    {
        app(AnthropicApiClient::class)->sendConversation('sistema', [['role' => 'user', 'content' => 'oi']], 4096, 'claude-sonnet-5');
        $this->assertSame('claude-sonnet-5', $this->modeloEnviado());
    }
}
