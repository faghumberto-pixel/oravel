<?php

namespace Tests\Feature;

use App\Models\Prospect;
use App\Relacionamento\Models\RelContato;
use App\Relacionamento\Models\RelConteudo;
use App\Relacionamento\Models\RelInteracao;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Relacionamento Oravel: sinal anonimo do navegador do oravel.org (ferramenta concluida, download) e o
 * vinculo retroativo quando a pessoa se identifica. DatabaseTransactions (ver Fase1Test).
 */
class RelacionamentoSiteEventoTest extends TestCase
{
    use DatabaseTransactions;

    private const VT = 'vt-navegador-abc123';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.relacionamento.site_hosts' => ['oravel.org'],
            'services.relacionamento.channels' => ['oravel-org' => ['token' => 'tok']],
        ]);
    }

    /** Simula sendBeacon: corpo JSON como text/plain, com Origin. */
    private function beacon(array $d, string $origin = 'https://oravel.org')
    {
        return $this->call('POST', '/api/relacionamento/site-evento', [], [], [], [
            'HTTP_ORIGIN' => $origin, 'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
        ], json_encode($d));
    }

    private function ferramenta(array $extra = []): array
    {
        return $extra + ['evento' => 'tool_complete', 'ferramenta' => 'preco-diaria', 'titulo' => 'Calculadora de Preço da Diária', 'segundos' => 120, 'uf' => 'sp', 'visitor' => self::VT];
    }

    public function test_ferramenta_concluida_vira_interacao_anonima(): void
    {
        $this->beacon($this->ferramenta())->assertNoContent();

        $i = RelInteracao::firstOrFail();
        $this->assertSame('ferramenta', $i->tipo);
        $this->assertSame('ferramentas/preco-diaria', $i->conteudo_slug);
        $this->assertSame(120, $i->duracao_segundos);
        $this->assertSame(self::VT, $i->visitor_token);
        $this->assertNull($i->contato_id);
        $this->assertSame('SP', $i->dados['uf']);
        $this->assertSame('ferramenta', RelConteudo::where('slug', 'ferramentas/preco-diaria')->value('tipo'));
    }

    public function test_download_vira_interacao_e_caminho_fora_da_lista_e_ignorado(): void
    {
        $this->beacon(['evento' => 'download', 'arquivo' => '/downloads/checklist-devolucao.html', 'visitor' => self::VT])->assertNoContent();
        $this->beacon(['evento' => 'download', 'arquivo' => '/admin/segredo.pdf', 'visitor' => self::VT])->assertNoContent();
        $this->beacon(['evento' => 'download', 'arquivo' => '/downloads/../etc/passwd', 'visitor' => self::VT])->assertNoContent();

        $this->assertSame(1, RelInteracao::count());
        $i = RelInteracao::firstOrFail();
        $this->assertSame('pdf', $i->tipo);
        $this->assertSame('downloads/checklist-devolucao', $i->conteudo_slug);
    }

    public function test_origem_nao_autorizada_e_sem_origem_nao_gravam(): void
    {
        $this->beacon($this->ferramenta(), 'https://evil.example')->assertNoContent();
        $this->call('POST', '/api/relacionamento/site-evento', [], [], [], ['CONTENT_TYPE' => 'text/plain'], json_encode($this->ferramenta()))->assertNoContent();

        $this->assertSame(0, RelInteracao::count());
    }

    public function test_payload_invalido_nunca_vira_erro_nem_grava(): void
    {
        $this->beacon(['evento' => 'tool_start', 'ferramenta' => 'x', 'visitor' => self::VT])->assertNoContent();        // evento nao aceito
        $this->beacon($this->ferramenta(['visitor' => 'curto']))->assertNoContent();                                    // token invalido
        $this->beacon($this->ferramenta(['ferramenta' => '../x']))->assertNoContent();                                  // slug invalido
        $this->call('POST', '/api/relacionamento/site-evento', [], [], [], ['HTTP_ORIGIN' => 'https://oravel.org'], '{lixo')->assertNoContent();

        $this->assertSame(0, RelInteracao::count());
    }

    public function test_beacon_nao_aceita_identificacao(): void
    {
        $this->beacon($this->ferramenta(['email' => 'vitima@x.com', 'telefone' => '(19) 98888-7777']))->assertNoContent();

        $this->assertSame(0, RelContato::count());
        $this->assertNull(RelInteracao::firstOrFail()->contato_id);
    }

    public function test_ao_se_identificar_acessos_anteriores_do_navegador_passam_para_a_pessoa_e_empresa(): void
    {
        $p = Prospect::create(['cnpj' => '12345678000199', 'nome' => 'Locadora Y', 'email' => 'dono@y.com']);
        $outro = $this->ferramenta(['visitor' => 'vt-de-outra-pessoa-9']);
        $this->beacon($this->ferramenta())->assertNoContent();
        $this->beacon($outro)->assertNoContent();

        $this->postJson('/api/relacionamento/eventos', ['tipo' => 'gazeta_inscricao', 'email' => 'dono@y.com', 'visitor_token' => self::VT], ['X-Channel-Token' => 'tok'])->assertStatus(201);

        $c = RelContato::where('email', 'dono@y.com')->firstOrFail();
        $minha = RelInteracao::where('tipo', 'ferramenta')->where('visitor_token', self::VT)->firstOrFail();
        $this->assertSame($c->id, $minha->contato_id);
        $this->assertSame($p->id, $minha->prospect_id);
        $alheia = RelInteracao::where('visitor_token', 'vt-de-outra-pessoa-9')->firstOrFail();
        $this->assertNull($alheia->contato_id);
        $this->assertNull($alheia->prospect_id);
    }

    public function test_acesso_anonimo_posterior_de_navegador_ja_identificado_cai_na_pessoa(): void
    {
        $this->postJson('/api/relacionamento/eventos', ['tipo' => 'gazeta_inscricao', 'email' => 'maria@z.com', 'visitor_token' => self::VT], ['X-Channel-Token' => 'tok'])->assertStatus(201);
        $this->beacon($this->ferramenta())->assertNoContent();

        $c = RelContato::where('email', 'maria@z.com')->firstOrFail();
        $this->assertSame($c->id, RelInteracao::where('tipo', 'ferramenta')->firstOrFail()->contato_id);
    }

    public function test_identificacao_nao_toma_acesso_que_ja_tem_dono(): void
    {
        $this->postJson('/api/relacionamento/eventos', ['tipo' => 'gazeta_inscricao', 'email' => 'a@a.com', 'visitor_token' => self::VT], ['X-Channel-Token' => 'tok'])->assertStatus(201);
        $this->beacon($this->ferramenta())->assertNoContent(); // fica da pessoa A
        $this->postJson('/api/relacionamento/eventos', ['tipo' => 'artigo', 'email' => 'b@b.com', 'visitor_token' => self::VT], ['X-Channel-Token' => 'tok'])->assertStatus(201);

        $a = RelContato::where('email', 'a@a.com')->firstOrFail();
        $this->assertSame($a->id, RelInteracao::where('tipo', 'ferramenta')->firstOrFail()->contato_id);
    }
}
