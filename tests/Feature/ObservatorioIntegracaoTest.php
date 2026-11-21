<?php

namespace Tests\Feature;

use App\Integracoes\Observatorio\EmpresaRef;
use App\Integracoes\Observatorio\HttpObservatorioGateway;
use App\Integracoes\Observatorio\ObservatorioGateway;
use App\Integracoes\Observatorio\ObservatorioIndisponivel;
use App\Integracoes\Observatorio\RegistrarEntrevista;
use App\Integracoes\Observatorio\ResultadoEnvio;
use App\Jobs\EnviarEventoObservatorio;
use App\Models\ObservatorioEnvio;
use App\Models\Prospect;
use App\Models\ProspectContato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ObservatorioIntegracaoTest extends TestCase
{
    use RefreshDatabase;

    private array $pesquisa = ['id' => 7, 'versao' => 3, 'titulo' => 'Desafios 2027', 'perguntas' => []];

    protected function setUp(): void
    {
        parent::setUp();
        config(['observatorio.url' => 'https://obs.test/api/v1', 'observatorio.token' => 'tok', 'observatorio.segredo' => 'seg', 'observatorio.ref_key' => 'chave-ref']);
        Cache::flush();
    }

    private function prospect(): Prospect
    {
        return Prospect::create([
            'cnpj' => '11222333000181', 'nome' => 'Locadora Sigilosa Ltda', 'razao_social' => 'Sigilosa Equipamentos', 'telefone' => '19999990000',
            'email' => 'dono@sigilosa.com.br', 'socio' => 'José da Silva', 'porte' => 'ME', 'atividade' => 'Locação de máquinas',
        ]);
    }

    private function registrar(Prospect $p, array $respostas = [1 => 'Mecânicos', 2 => ['A', 'B']]): ObservatorioEnvio
    {
        return app(RegistrarEntrevista::class)->executar($p, $this->pesquisa, [
            'canal' => 'telefone', 'uf' => 'sp', 'contato_nome' => 'José da Silva', 'observacao_livre' => 'Dificuldade para contratar.',
        ], $respostas, null);
    }

    public function test_payload_nao_contem_dado_que_identifique_a_empresa_ou_a_pessoa(): void
    {
        Queue::fake();
        $envio = $this->registrar($this->prospect());
        $json = json_encode($envio->payload, JSON_UNESCAPED_UNICODE);

        foreach (['Sigilosa', '11222333000181', '19999990000', 'sigilosa.com.br', 'José da Silva', $envio->prospect_id] as $proibido) {
            $this->assertStringNotContainsString($proibido, $json, "vazou: $proibido");
        }
        $data = $envio->payload['data'];
        $this->assertSame(64, strlen($data['empresa_ref']));
        $this->assertSame(['porte' => 'ME', 'uf' => 'SP', 'segmento' => 'Locação de máquinas'], $data['segmentacao']);
        $this->assertSame([['pergunta_id' => 1, 'valor' => 'Mecânicos'], ['pergunta_id' => 2, 'valores' => ['A', 'B']]], $data['respostas']);
        $this->assertEqualsCanonicalizing(['entrevista_ref', 'pesquisa_id', 'pesquisa_versao', 'empresa_ref', 'segmentacao', 'canal', 'registrado_em', 'respostas', 'observacao_livre'], array_keys($data));
    }

    public function test_empresa_ref_e_estavel_e_depende_da_chave(): void
    {
        $a = EmpresaRef::de('prospect-1');
        $this->assertSame($a, EmpresaRef::de('prospect-1'));
        $this->assertNotSame($a, EmpresaRef::de('prospect-2'));
        config(['observatorio.ref_key' => 'outra']);
        $this->assertNotSame($a, EmpresaRef::de('prospect-1'));
        config(['observatorio.ref_key' => '']);
        $this->expectException(ObservatorioIndisponivel::class);
        EmpresaRef::de('prospect-1');
    }

    public function test_registra_atividade_no_crm_sem_respostas_e_enfileira_o_envio(): void
    {
        Queue::fake();
        $p = $this->prospect();
        $envio = $this->registrar($p);

        $c = ProspectContato::where('prospect_id', $p->id)->firstOrFail();
        $this->assertSame('Entrevista do Observatório: Desafios 2027', $c->titulo);
        $this->assertStringNotContainsString('Mecânicos', $c->texto);
        $this->assertStringNotContainsString('contratar', $c->texto);
        $this->assertNotNull($p->fresh()->ultimo_contato_em);
        $this->assertSame('pendente', $envio->status);
        Queue::assertPushed(EnviarEventoObservatorio::class, fn ($j) => $j->envioId === $envio->id);
    }

    public function test_envio_assina_o_corpo_exato_e_apaga_o_payload_apos_entrega(): void
    {
        Http::fake(['obs.test/*' => Http::response(['status' => 'accepted'], 202)]);
        Queue::fake();
        $envio = $this->registrar($this->prospect());

        (new EnviarEventoObservatorio($envio->id))->handle(app(ObservatorioGateway::class));

        Http::assertSent(function (Request $r) {
            $esperada = hash_hmac('sha256', $r->header('X-Oravel-Timestamp')[0].'.'.$r->body(), 'seg');

            return $r->url() === 'https://obs.test/api/v1/eventos' && $r->hasHeader('Authorization', 'Bearer tok')
                && $r->header('X-Oravel-Signature')[0] === $esperada && json_decode($r->body(), true)['event_type'] === 'entrevista.registrada';
        });
        $envio->refresh();
        $this->assertSame('enviado', $envio->status);
        $this->assertNull($envio->payload, 'respostas nao podem ficar na Central');
        $this->assertNotNull($envio->enviado_em);
    }

    public function test_duplicado_200_conta_como_entregue(): void
    {
        Http::fake(['obs.test/*' => Http::response(['status' => 'duplicate'], 200)]);
        Queue::fake();
        $envio = $this->registrar($this->prospect());
        (new EnviarEventoObservatorio($envio->id))->handle(app(ObservatorioGateway::class));

        $this->assertSame('enviado', $envio->fresh()->status);
    }

    public function test_falha_transitoria_reagenda_com_backoff_e_esgota_em_falhou(): void
    {
        Http::fake(['obs.test/*' => Http::response('erro', 503)]);
        Queue::fake();
        config(['observatorio.max_tentativas' => 3]);
        $envio = $this->registrar($this->prospect());
        $gw = app(ObservatorioGateway::class);

        (new EnviarEventoObservatorio($envio->id))->handle($gw);
        $envio->refresh();
        $this->assertSame('pendente', $envio->status);
        $this->assertSame(1, $envio->tentativas);
        $this->assertTrue($envio->proxima_tentativa_em->isFuture());
        $this->assertNotNull($envio->payload);

        (new EnviarEventoObservatorio($envio->id))->handle($gw);
        (new EnviarEventoObservatorio($envio->id))->handle($gw);
        $envio->refresh();
        $this->assertSame('falhou', $envio->status);
        $this->assertNull($envio->proxima_tentativa_em);
        $this->assertNotNull($envio->payload, 'falhou mantem o payload para reenvio manual');
    }

    public function test_queda_de_rede_nao_perde_o_evento(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        Queue::fake();
        $envio = $this->registrar($this->prospect());
        (new EnviarEventoObservatorio($envio->id))->handle(app(ObservatorioGateway::class));

        $envio->refresh();
        $this->assertSame('pendente', $envio->status);
        $this->assertNotNull($envio->payload);
    }

    public function test_422_rejeita_sem_reenviar_e_409_pede_refazer(): void
    {
        Queue::fake();
        $gw = app(ObservatorioGateway::class);

        Http::fake(['obs.test/*' => Http::sequence()->push(['erro' => 'contrato_invalido'], 422)->push(['erro' => 'pesquisa_versao_incompativel', 'versao_atual' => 4], 409)]);
        $a = $this->registrar($this->prospect());
        (new EnviarEventoObservatorio($a->id))->handle($gw);
        $this->assertSame('rejeitado', $a->fresh()->status);
        $this->assertNull($a->fresh()->proxima_tentativa_em);
        Http::assertSentCount(1);

        $b = $this->registrar(Prospect::create(['cnpj' => '11444777000161', 'nome' => 'Outra']));
        Cache::put('observatorio.pesquisas_ativas', ['velho'], 300);
        (new EnviarEventoObservatorio($b->id))->handle($gw);
        $this->assertSame('versao_desatualizada', $b->fresh()->status);
        $this->assertNull($b->fresh()->proxima_tentativa_em);
        $this->assertFalse(Cache::has('observatorio.pesquisas_ativas'), 'definicao em cache deve ser descartada');
    }

    public function test_job_ignora_envio_que_nao_esta_pendente(): void
    {
        Http::fake();
        Queue::fake();
        $envio = $this->registrar($this->prospect());
        $envio->update(['status' => 'enviado', 'payload' => null]);

        (new EnviarEventoObservatorio($envio->id))->handle(app(ObservatorioGateway::class));

        Http::assertNothingSent();
    }

    public function test_pesquisas_ativas_usa_cache_e_assina_get_com_corpo_vazio(): void
    {
        Http::fake(['obs.test/*' => Http::response(['pesquisas' => [['id' => 7, 'versao' => 3, 'titulo' => 'X', 'perguntas' => []]]], 200)]);
        $gw = app(ObservatorioGateway::class);

        $this->assertSame(7, $gw->pesquisasAtivas()[0]['id']);
        $gw->pesquisasAtivas();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->method() === 'GET'
            && $r->header('X-Oravel-Signature')[0] === hash_hmac('sha256', $r->header('X-Oravel-Timestamp')[0].'.', 'seg'));
    }

    public function test_pesquisas_ativas_sem_configuracao_ou_com_erro_lanca_indisponivel(): void
    {
        config(['observatorio.url' => null]);
        try {
            app(ObservatorioGateway::class)->pesquisasAtivas();
            $this->fail('deveria lancar');
        } catch (ObservatorioIndisponivel $e) {
            $this->assertStringContainsString('não configurada', $e->getMessage());
        }

        config(['observatorio.url' => 'https://obs.test/api/v1']);
        HttpObservatorioGateway::esquecerCache();
        Http::fake(['obs.test/*' => Http::response('x', 500)]);
        $this->expectException(ObservatorioIndisponivel::class);
        app(ObservatorioGateway::class)->pesquisasAtivas();
    }

    public function test_comando_reenviar_so_enfileira_pendentes_vencidos(): void
    {
        Queue::fake();
        $vencido = $this->registrar($this->prospect());
        $futuro = $this->registrar(Prospect::create(['cnpj' => '11444777000161', 'nome' => 'B']));
        $vencido->update(['proxima_tentativa_em' => now()->subMinute()]);
        $futuro->update(['proxima_tentativa_em' => now()->addHour()]);
        Queue::fake(); // zera os dispatches do registro

        $this->artisan('observatorio:reenviar')->assertSuccessful();

        Queue::assertPushed(EnviarEventoObservatorio::class, 1);
        Queue::assertPushed(EnviarEventoObservatorio::class, fn ($j) => $j->envioId === $vencido->id);
    }

    public function test_falha_inesperada_no_gateway_vira_nova_tentativa_e_nao_estoura_a_requisicao(): void
    {
        Queue::fake();
        $envio = $this->registrar($this->prospect());
        $quebrado = new class implements ObservatorioGateway
        {
            public function pesquisasAtivas(): array
            {
                return [];
            }

            public function enviar(array $envelope): ResultadoEnvio
            {
                throw new \RuntimeException('algo inesperado com a resposta do servidor');
            }
        };

        (new EnviarEventoObservatorio($envio->id))->handle($quebrado); // nao pode lancar

        $envio->refresh();
        $this->assertSame(['pendente', 1], [$envio->status, $envio->tentativas]);
        $this->assertStringContainsString('Falha inesperada: algo inesperado', $envio->ultimo_erro);
        $this->assertNotNull($envio->payload, 'o evento nao se perde');
        $this->assertTrue($envio->proxima_tentativa_em->isFuture());
    }

    public function test_com_fila_sync_o_envio_acontece_na_hora_e_o_payload_some(): void
    {
        config(['queue.default' => 'sync']);
        Http::fake(['obs.test/*' => Http::response(['status' => 'accepted'], 202)]);

        $envio = $this->registrar($this->prospect());

        $envio->refresh();
        $this->assertSame('enviado', $envio->status);
        $this->assertNull($envio->payload);
        Http::assertSentCount(1);
    }

    public function test_com_fila_sync_e_observatorio_fora_do_ar_o_registro_fica_salvo_e_pendente(): void
    {
        config(['queue.default' => 'sync']);
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $p = $this->prospect();

        $envio = $this->registrar($p); // nao pode lancar

        $this->assertSame('pendente', $envio->fresh()->status);
        $this->assertSame(1, ProspectContato::where('prospect_id', $p->id)->count(), 'a atividade do CRM fica salva');
    }
}
