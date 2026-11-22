<?php

namespace Tests\Unit\Assistente;

use App\Support\Assistente\Sessao;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Puro (sem app/banco): vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/Assistente/SessaoTest.php */
class SessaoTest extends TestCase
{
    public static function casos(): array
    {
        $token = str_repeat('a', 48);

        return [
            'tela inicial do Prospects' => ['GET', '/assistente', [], [], 'prospects'],
            'login sem area = Prospects' => ['GET', '/assistente/login', [], [], 'prospects'],
            'login do Suprimentos (query)' => ['GET', '/assistente/login?area=suprimentos', [], [], 'suprimentos'],
            'envio do login do Suprimentos' => ['POST', '/assistente/login', ['area' => 'suprimentos'], [], 'suprimentos'],
            'envio do login do Prospects' => ['POST', '/assistente/login', ['area' => ''], [], 'prospects'],
            'sair do Suprimentos' => ['POST', '/assistente/logout', ['area' => 'suprimentos'], [], 'suprimentos'],
            'sair do Prospects' => ['POST', '/assistente/logout', [], [], 'prospects'],
            'tela do Suprimentos' => ['GET', '/assistente/suprimentos', [], [], 'suprimentos'],
            'login do Intelligence (query)' => ['GET', '/assistente/login?area=intelligence', [], [], 'intelligence'],
            'envio do login do Intelligence' => ['POST', '/assistente/login', ['area' => 'intelligence'], [], 'intelligence'],
            'sair do Intelligence' => ['POST', '/assistente/logout', ['area' => 'intelligence'], [], 'intelligence'],
            'tela do Intelligence' => ['GET', '/assistente/intelligence', [], [], 'intelligence'],
            'livewire vindo do Intelligence' => ['POST', '/livewire/update', [], ['referer' => 'https://app.oravel.com.br/assistente/intelligence'], 'intelligence'],
            'area desconhecida cai em Prospects' => ['GET', '/assistente/login?area=xyz', [], [], 'prospects'],
            'relatorio publico nao usa sessao de app' => ['GET', "/assistente/r/{$token}", [], [], null],
            'painel admin' => ['GET', '/admin', [], [], null],
            'central' => ['GET', '/central/prospects', [], [], null],
            'site' => ['GET', '/', [], [], null],
            'livewire vindo do Suprimentos' => ['POST', '/livewire/update', [], ['referer' => 'https://app.oravel.com.br/assistente/suprimentos'], 'suprimentos'],
            'livewire vindo do Prospects' => ['POST', '/livewire/update', [], ['referer' => 'https://app.oravel.com.br/assistente'], 'prospects'],
            'livewire vindo do painel admin' => ['POST', '/livewire/update', [], ['referer' => 'https://app.oravel.com.br/admin/clients'], null],
            'livewire vindo da central' => ['POST', '/livewire/update', [], ['referer' => 'https://app.oravel.com.br/central/prospects'], null],
            'livewire sem referer' => ['POST', '/livewire/update', [], [], null],
            'prefixo parecido nao conta' => ['GET', '/assistentes', [], [], null],
        ];
    }

    #[DataProvider('casos')]
    public function test_area_da_requisicao(string $metodo, string $uri, array $dados, array $cabecalhos, ?string $esperada): void
    {
        $request = Request::create($uri, $metodo, $dados);
        foreach ($cabecalhos as $k => $v) {
            $request->headers->set($k, $v);
        }

        $this->assertSame($esperada, Sessao::area($request));
    }
}
