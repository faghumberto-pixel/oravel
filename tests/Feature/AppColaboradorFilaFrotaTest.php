<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\FleetDriver;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaPercurso;
use App\Models\FrotaSaidaVeiculo;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\KmRodadoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Logística offline: entrada/saída, abastecimento e marcar km vindos do celular pela fila de envio. */
class AppColaboradorFilaFrotaTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private User $u;

    private Asset $veiculo;

    private Client $cliente;

    private FleetDriver $motorista;

    protected function setUp(): void
    {
        parent::setUp();
        $plan = Plan::create(['name' => 'Plano FF '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets', 'tabela_frota_saidas_veiculo', 'tabela_frota_abastecimentos', 'tabela_frota_vinculos_motorista', 'tabela_frota_percursos', 'modulo_app_colaborador']]);
        $t = Tenant::create(['name' => 'Locadora FF', 'slug' => 'ff-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $this->u = User::create(['name' => 'Gestor', 'email' => 'ff-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id]);
        $this->u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $this->u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        $this->veiculo = Asset::create(['tenant_id' => $t->id, 'name' => 'Munck', 'placa' => 'FFF1A11', 'grupo' => Asset::GRUPO_VEICULO, 'status' => 'disponivel', 'odometro_atual' => 1000]);
        $this->cliente = Client::create(['tenant_id' => $t->id, 'name' => 'Cliente FF', 'email' => 'c-'.uniqid().'@t.com', 'password' => 'senha123']);
        $this->motorista = FleetDriver::create(['tenant_id' => $t->id, 'name' => 'Zé', 'active' => true]);
    }

    private function enviar(array $acoes)
    {
        return $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($this->u)->postJson('/colaborador/api/fila', ['acoes' => $acoes]);
    }

    private function acao(string $tipo, array $dados): array
    {
        return ['uuid' => (string) Str::uuid(), 'tipo' => $tipo, 'dados' => $dados, 'criado_em' => now()->toDateTimeString()];
    }

    public function test_pacote_traz_a_frota_com_situacao_dos_veiculos_motoristas_clientes_e_percursos(): void
    {
        FrotaPercurso::create(['tenant_id' => $this->u->tenant_id, 'nome' => 'Empresa ↔ FF', 'local_b_tipo' => 'cliente', 'local_b_cliente_id' => $this->cliente->id, 'km_previsto' => 40]);

        $j = $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($this->u)->getJson('/colaborador/api/pacote')->assertOk()->json('frota');

        $this->assertTrue($j['perm']['saida'] && $j['perm']['abastecimento']);
        $v = collect($j['veiculos'])->firstWhere('id', $this->veiculo->id);
        $this->assertSame(['FFF1A11', 1000, 'empresa', null], [$v['pl'], $v['odo'], $v['local']['tipo'], $v['aberta']]);
        $this->assertSame($this->motorista->id, $j['motoristas'][0]['id']);
        $this->assertSame($this->cliente->id, $j['clientes'][0]['id']);
        $this->assertSame('cliente', $j['percursos'][0]['b']['tipo']);
        $this->assertArrayHasKey('visita_tecnica', $j['rotulos']['finalidades']);
        $this->assertArrayHasKey('diesel_s10', $j['rotulos']['combustiveis']);
    }

    public function test_ciclo_offline_saida_entrada_abastecimento_e_km_chega_na_ordem_com_a_hora_do_evento(): void
    {
        $saidaEm = now()->subHours(6)->toDateTimeString();
        $entradaEm = now()->subHours(5)->toDateTimeString();
        $abastEm = now()->subHours(4)->toDateTimeString();
        $kmEm = now()->subHours(3)->toDateTimeString();
        $v = $this->veiculo->id;

        $r = $this->enviar([
            $this->acao('veiculo_saida', ['ativo_id' => $v, 'finalidade' => 'cliente', 'motorista_id' => $this->motorista->id, 'origem_tipo' => 'empresa', 'destino_tipo' => 'cliente', 'cliente_id' => $this->cliente->id, 'motivo' => 'Entrega', 'saida_em' => $saidaEm, 'odometro' => 1000]),
            $this->acao('veiculo_entrada', ['ativo_id' => $v, 'retorno_em' => $entradaEm, 'odometro' => 1042]),
            $this->acao('abastecimento', ['ativo_id' => $v, 'abastecido_em' => $abastEm, 'odometro' => 1050, 'combustivel' => 'diesel_s10', 'litros' => 40, 'valor_total' => 240, 'tanque_cheio' => true, 'origem' => 'externo']),
            $this->acao('km_marcar', ['ativo_id' => $v, 'odometro' => 1060, 'lido_em' => $kmEm]),
        ])->assertOk();

        $this->assertSame(['ok', 'ok', 'ok', 'ok'], array_column($r->json('resultados'), 'status'), json_encode($r->json('resultados')));

        $saida = FrotaSaidaVeiculo::where('ativo_id', $v)->first();
        $this->assertSame(42, $saida->kmRodado());
        $this->assertSame('cliente', $this->veiculo->refresh()->localAtual()['tipo'], 'o veículo ficou no cliente');
        $this->assertSame(1, FrotaAbastecimento::where('ativo_id', $v)->count());
        $this->assertSame(1060, (int) $this->veiculo->odometro_atual);

        // A hora gravada é a do evento no celular, na ordem em que aconteceu (não a do envio).
        $leituras = FrotaLeituraOdometro::where('ativo_id', $v)->orderBy('lido_em')->get();
        $this->assertSame([1000, 1042, 1050, 1060], $leituras->pluck('odometro')->all());
        $this->assertSame(substr($saidaEm, 0, 16), $leituras->first()->lido_em->format('Y-m-d H:i'));
        $this->assertSame(60, app(KmRodadoService::class)->porVeiculo($this->veiculo->refresh())['total']);
    }

    public function test_entrada_repetida_ou_sem_saida_e_ignorada_e_acao_reenviada_nao_duplica(): void
    {
        $v = $this->veiculo->id;
        $entrada = $this->acao('veiculo_entrada', ['ativo_id' => $v, 'retorno_em' => now()->toDateTimeString(), 'odometro' => 1100]);

        $r = $this->enviar([$entrada])->assertOk();
        $this->assertStringContainsString('não estava fora', $r->json('resultados.0.mensagem'));

        $km = $this->acao('km_marcar', ['ativo_id' => $v, 'odometro' => 1010, 'lido_em' => now()->subHour()->toDateTimeString()]);
        $this->enviar([$km])->assertOk();
        $this->enviar([$km])->assertOk();
        $this->assertSame(1, FrotaLeituraOdometro::where('ativo_id', $v)->where('odometro', 1010)->count());
    }

    public function test_regras_do_servidor_viram_erro_na_acao_e_o_que_nao_e_veiculo_e_recusado(): void
    {
        $v = $this->veiculo->id;

        $semDestino = $this->enviar([$this->acao('veiculo_saida', ['ativo_id' => $v, 'finalidade' => 'cliente', 'motorista_id' => $this->motorista->id, 'destino_tipo' => 'cliente', 'motivo' => 'x', 'saida_em' => now()->subHour()->toDateTimeString(), 'odometro' => 1000])])->assertOk();
        $this->assertSame('erro', $semDestino->json('resultados.0.status'));
        $this->assertStringContainsString('Escolha o cliente', $semDestino->json('resultados.0.mensagem'));

        $semLitros = $this->enviar([$this->acao('abastecimento', ['ativo_id' => $v, 'odometro' => 1000, 'combustivel' => 'diesel_s10', 'litros' => 0])])->assertOk();
        $this->assertSame('erro', $semLitros->json('resultados.0.status'));

        $naoVeiculo = Asset::create(['tenant_id' => $this->u->tenant_id, 'name' => 'Empilhadeira', 'status' => 'disponivel']);
        $r = $this->enviar([$this->acao('km_marcar', ['ativo_id' => $naoVeiculo->id, 'odometro' => 5])])->assertOk();
        $this->assertSame('Veículo não encontrado.', $r->json('resultados.0.mensagem'));
        $this->assertSame(0, FrotaSaidaVeiculo::count());
    }
}
