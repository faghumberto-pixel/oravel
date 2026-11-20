<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\WebIpIgnoradoResource;
use App\Filament\Central\Resources\WebIpIgnoradoResource\Pages\ManageWebIpsIgnorados;
use App\Models\User;
use App\Models\WebDiscard;
use App\Models\WebIpIgnorado;
use App\Models\WebPageview;
use App\Models\WebVisit;
use App\Relacionamento\Models\RelInteracao;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/** IPs ignorados (equipe/empresa): o coletor nao conta, e a tela apaga o que ja foi gravado. DatabaseTransactions. */
class WebIpIgnoradoTest extends TestCase
{
    use DatabaseTransactions;

    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.relacionamento.site_hosts' => ['oravel.org']]);
        Cache::forget('web-ips-ignorados');
    }

    private function pv(string $sessao, string $ip, string $origin = 'https://oravel.org')
    {
        Cache::put("ip-geo:{$ip}", ['city' => 'Campinas', 'state' => 'SP', 'hosting' => false]);

        return $this->call('POST', '/api/site-track', [], [], [], [
            'HTTP_ORIGIN' => $origin, 'HTTP_USER_AGENT' => self::UA, 'REMOTE_ADDR' => $ip, 'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
        ], json_encode(['t' => 'pv', 'v' => 'visitor-'.$sessao, 's' => $sessao, 'p' => 'page-'.$sessao, 'path' => '/ferramentas/preco-diaria/', 'title' => 'Calculadora']));
    }

    private function visita(string $sessao, string $ip, string $site = 'oravel.org'): WebVisit
    {
        return WebVisit::create(['site' => $site, 'visitor_token' => 'v-'.$sessao, 'session_token' => $sessao, 'ip_address' => $ip,
            'started_at' => now(), 'last_activity_at' => now(), 'page_views' => 1, 'duration_seconds' => 20, 'landing_path' => '/']);
    }

    private function admin(): User
    {
        config(['oravel.super_admins' => ['equipe@oravel.test']]);
        Filament::setCurrentPanel(Filament::getPanel('central'));

        return User::factory()->create(['email' => 'equipe@oravel.test']);
    }

    public function test_visita_de_ip_ignorado_nao_e_gravada_e_a_de_outro_ip_e(): void
    {
        WebIpIgnorado::create(['ip' => '201.10.10.10', 'rotulo' => 'Meu IP']);

        $this->pv('sess-meu-0001', '201.10.10.10')->assertNoContent();
        $this->pv('sess-cliente-01', '201.20.20.20')->assertNoContent();

        $this->assertSame(0, WebVisit::where('session_token', 'sess-meu-0001')->count());
        $this->assertSame(1, WebVisit::where('session_token', 'sess-cliente-01')->count());
    }

    public function test_vale_para_os_dois_sites_e_a_sessao_inteira_some(): void
    {
        WebIpIgnorado::create(['ip' => '201.10.10.10']);

        $this->pv('sess-org-00001', '201.10.10.10', 'https://oravel.org')->assertNoContent();
        $this->pv('sess-com-00001', '201.10.10.10', 'https://oravel.com.br')->assertNoContent();
        $this->call('POST', '/api/site-track', [], [], [], ['HTTP_ORIGIN' => 'https://oravel.org', 'HTTP_USER_AGENT' => self::UA, 'REMOTE_ADDR' => '201.10.10.10', 'CONTENT_TYPE' => 'text/plain'],
            json_encode(['t' => 'ping', 'v' => 'visitor-sess-org-00001', 's' => 'sess-org-00001', 'p' => 'page-sess-org-00001', 'ms' => 30000, 'sc' => 50]))->assertNoContent();

        $this->assertSame(0, WebVisit::whereIn('session_token', ['sess-org-00001', 'sess-com-00001'])->count());
        $this->assertSame(0, WebPageview::count());
    }

    public function test_faixa_cidr_ignora_a_rede_inteira_e_nao_a_vizinha(): void
    {
        WebIpIgnorado::create(['ip' => '200.50.60.0/24', 'rotulo' => 'Escritório']);

        $this->pv('sess-rede-00001', '200.50.60.17')->assertNoContent();
        $this->pv('sess-rede-00002', '200.50.60.254')->assertNoContent();
        $this->pv('sess-vizinha-01', '200.50.61.17')->assertNoContent();

        $this->assertSame(0, WebVisit::whereIn('session_token', ['sess-rede-00001', 'sess-rede-00002'])->count());
        $this->assertSame(1, WebVisit::where('session_token', 'sess-vizinha-01')->count());
    }

    public function test_faixa_ipv6(): void
    {
        WebIpIgnorado::create(['ip' => '2001:db8::/32']);

        $this->assertTrue(WebIpIgnorado::ignora('2001:db8:abcd::1'));
        $this->assertFalse(WebIpIgnorado::ignora('2001:db9::1'));
    }

    public function test_adicionar_ou_remover_vale_na_hora_apesar_do_cache(): void
    {
        $this->pv('sess-antes-0001', '201.10.10.10')->assertNoContent();
        $this->assertSame(1, WebVisit::where('session_token', 'sess-antes-0001')->count());

        $ip = WebIpIgnorado::create(['ip' => '201.10.10.10']); // a lista ja estava em cache, vazia
        $this->pv('sess-depois-001', '201.10.10.10')->assertNoContent();
        $this->assertSame(0, WebVisit::where('session_token', 'sess-depois-001')->count());

        $ip->delete();
        $this->pv('sess-volta-0001', '201.10.10.10')->assertNoContent();
        $this->assertSame(1, WebVisit::where('session_token', 'sess-volta-0001')->count());
    }

    public function test_o_descarte_e_contado_com_o_motivo_proprio(): void
    {
        WebIpIgnorado::create(['ip' => '201.10.10.10']);
        $this->pv('sess-meu-0002', '201.10.10.10');

        $this->assertSame(1, (int) WebDiscard::where('reason', WebDiscard::REASON_IP_IGNORADO)->sum('hits'));
        $this->assertArrayHasKey(WebDiscard::REASON_IP_IGNORADO, WebDiscard::reasonLabels());
    }

    public function test_sinal_de_ferramentas_e_downloads_tambem_ignora(): void
    {
        config(['services.relacionamento.channels' => ['oravel-org' => ['token' => 't']]]);
        WebIpIgnorado::create(['ip' => '201.10.10.10']);
        $corpo = json_encode(['evento' => 'tool_complete', 'ferramenta' => 'preco-diaria', 'titulo' => 'Calculadora', 'segundos' => 60, 'visitor' => 'visitor-abc-12345']);

        $this->call('POST', '/api/relacionamento/site-evento', [], [], [], ['HTTP_ORIGIN' => 'https://oravel.org', 'REMOTE_ADDR' => '201.10.10.10', 'CONTENT_TYPE' => 'text/plain'], $corpo)->assertNoContent();
        $this->assertSame(0, RelInteracao::count());

        $this->call('POST', '/api/relacionamento/site-evento', [], [], [], ['HTTP_ORIGIN' => 'https://oravel.org', 'REMOTE_ADDR' => '201.30.30.30', 'CONTENT_TYPE' => 'text/plain'], $corpo)->assertNoContent();
        $this->assertSame(1, RelInteracao::count());
    }

    public function test_validacao_de_ip_e_faixa(): void
    {
        foreach (['201.10.10.10', '2001:db8::1', '200.50.60.0/24', '10.0.0.0/16', '2001:db8::/32'] as $ok) {
            $this->assertTrue(WebIpIgnorado::entradaValida($ok), $ok);
        }
        foreach (['', 'abc', '999.1.1.1', '200.50.60.0/8', '0.0.0.0/0', '200.50.60.0/33', '200.50.60.0/x', '2001:db8::/16'] as $ruim) {
            $this->assertFalse(WebIpIgnorado::entradaValida($ruim), "deveria recusar: {$ruim}");
        }
    }

    public function test_tela_cria_o_ip_e_apaga_o_historico_so_desse_ip(): void
    {
        $meu = $this->visita('s-meu-1', '201.10.10.10');
        WebPageview::create(['web_visit_id' => $meu->id, 'page_token' => 'pg-meu', 'path' => '/x', 'entered_at' => now()]);
        $meuCom = $this->visita('s-meu-2', '201.10.10.10', 'oravel.com.br');
        $outro = $this->visita('s-outro', '201.20.20.20');

        Livewire::actingAs($this->admin())->test(ManageWebIpsIgnorados::class)
            ->callAction('create', data: ['ip' => '201.10.10.10', 'rotulo' => 'Meu IP', 'apagar_historico' => true])
            ->assertHasNoActionErrors();

        $this->assertTrue(WebIpIgnorado::ignora('201.10.10.10'));
        $this->assertNull(WebVisit::find($meu->id), 'a visita de agora some');
        $this->assertNull(WebVisit::find($meuCom->id), 'vale para os dois sites');
        $this->assertSame(0, WebPageview::where('page_token', 'pg-meu')->count(), 'os pageviews vao junto');
        $this->assertNotNull(WebVisit::find($outro->id), 'visita de outro IP fica');
    }

    public function test_tela_sem_apagar_historico_mantem_o_que_ja_existe(): void
    {
        $meu = $this->visita('s-meu-1', '201.10.10.10');

        Livewire::actingAs($this->admin())->test(ManageWebIpsIgnorados::class)
            ->callAction('create', data: ['ip' => '201.10.10.10', 'apagar_historico' => false])->assertHasNoActionErrors();

        $this->assertNotNull(WebVisit::find($meu->id));
        $this->assertTrue(WebIpIgnorado::ignora('201.10.10.10'));
    }

    public function test_tela_com_faixa_apaga_o_historico_de_toda_a_rede(): void
    {
        $a = $this->visita('s-a', '200.50.60.5');
        $b = $this->visita('s-b', '200.50.60.200');
        $fora = $this->visita('s-fora', '200.50.61.5');

        Livewire::actingAs($this->admin())->test(ManageWebIpsIgnorados::class)
            ->callAction('create', data: ['ip' => '200.50.60.0/24', 'rotulo' => 'Escritório', 'apagar_historico' => true])->assertHasNoActionErrors();

        $this->assertNull(WebVisit::find($a->id));
        $this->assertNull(WebVisit::find($b->id));
        $this->assertNotNull(WebVisit::find($fora->id));
    }

    public function test_tela_recusa_entrada_invalida_e_duplicada(): void
    {
        WebIpIgnorado::create(['ip' => '201.10.10.10']);
        $t = Livewire::actingAs($this->admin())->test(ManageWebIpsIgnorados::class);

        $t->callAction('create', data: ['ip' => 'isso-nao-e-ip'])->assertHasActionErrors(['ip']);
        $t->callAction('create', data: ['ip' => '0.0.0.0/0'])->assertHasActionErrors(['ip']);
        $t->callAction('create', data: ['ip' => '201.10.10.10'])->assertHasActionErrors(['ip']);
        $this->assertSame(1, WebIpIgnorado::count());
    }

    public function test_so_super_admin_ve_a_tela(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'comum@x.test']));
        $this->assertFalse(WebIpIgnoradoResource::canViewAny());

        $this->actingAs($this->admin());
        $this->assertTrue(WebIpIgnoradoResource::canViewAny());
    }
}
