<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * O site passa pelo Cloudflare: o IP real do visitante vem no X-Forwarded-For,
 * confiável só quando a conexão vem de uma faixa do Cloudflare. Antes,
 * request()->ip() devolvia o IP do Cloudflare (cidade errada nas visitas,
 * "Bloquear IP" capaz de bloquear o Cloudflare inteiro).
 */
class CloudflareRealIpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/__ip-teste', fn () => response(request()->ip()));
    }

    public function test_real_client_ip_is_used_when_the_connection_comes_from_cloudflare(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.69.138.18'])
            ->withHeader('X-Forwarded-For', '201.27.224.130')
            ->get('/__ip-teste')
            ->assertOk()
            ->assertSee('201.27.224.130');

        $this->withServerVariables(['REMOTE_ADDR' => '104.22.10.8'])
            ->withHeader('X-Forwarded-For', '2804:14d:1:2::3')
            ->get('/__ip-teste')
            ->assertSee('2804:14d:1:2::3');
    }

    public function test_a_fake_forwarded_for_header_cannot_spoof_the_ip(): void
    {
        // Conexão direta ao servidor (fora do Cloudflare): o cabeçalho é ignorado.
        $this->withServerVariables(['REMOTE_ADDR' => '45.79.8.221'])
            ->withHeader('X-Forwarded-For', '1.2.3.4')
            ->get('/__ip-teste')
            ->assertSee('45.79.8.221');

        // Pelo Cloudflare, o cliente tenta forjar: o Cloudflare acrescenta o IP real
        // ao fim da cadeia, e vale o IP real, não o forjado.
        $this->withServerVariables(['REMOTE_ADDR' => '172.69.138.18'])
            ->withHeader('X-Forwarded-For', '9.9.9.9, 201.27.224.130')
            ->get('/__ip-teste')
            ->assertSee('201.27.224.130');
    }

    public function test_blocking_a_real_visitor_ip_does_not_block_other_visitors_through_the_same_cloudflare_edge(): void
    {
        BlockedIp::create(['ip_address' => '66.228.53.204', 'reason' => 'robô']);

        // Outro visitante pelo MESMO IP de borda do Cloudflare passa.
        $this->withServerVariables(['REMOTE_ADDR' => '172.69.138.18'])
            ->withHeader('X-Forwarded-For', '201.27.224.130')
            ->get('/__ip-teste')
            ->assertOk();

        // O IP bloqueado, mesmo chegando pelo Cloudflare, é barrado.
        $this->withServerVariables(['REMOTE_ADDR' => '172.69.138.18'])
            ->withHeader('X-Forwarded-For', '66.228.53.204')
            ->get('/__ip-teste')
            ->assertForbidden();
    }
}
