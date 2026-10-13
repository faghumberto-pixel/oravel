<?php

namespace Tests\Feature;

use App\Filament\Pages\KmRodadoFrota;
use App\Filament\Resources\FrotaPercursoResource\Pages\ListFrotaPercursos;
use App\Models\Asset;
use App\Models\Client;
use App\Models\FleetDriver;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaPercurso;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\KmRodadoService;
use App\Services\Frota\SaidaVeiculoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Trechos (empresa ↔ cliente), percursos e o cálculo de km rodado. */
class FrotaPercursoKmRodadoTest extends TestCase
{
    use DatabaseTransactions;

    private User $u;

    private Asset $veiculo;

    private Client $cliente;

    private FleetDriver $motorista;

    protected function setUp(): void
    {
        parent::setUp();
        $plan = Plan::create(['name' => 'Plano Km '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets', 'tabela_frota_saidas_veiculo', 'tabela_frota_percursos', 'modulo_app_colaborador']]);
        $tenant = Tenant::create(['name' => 'Locadora Km', 'slug' => 'km-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $this->u = User::create(['name' => 'Gestor', 'email' => 'km-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $this->u->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $this->u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->actingAs($this->u);

        $this->veiculo = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Munck', 'placa' => 'KMK1A11', 'grupo' => Asset::GRUPO_VEICULO, 'status' => 'disponivel', 'odometro_atual' => 1000, 'cost_per_km' => 2]);
        $this->cliente = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Topmixx', 'email' => 'c-'.uniqid().'@t.com', 'password' => 'senha123']);
        $this->motorista = FleetDriver::create(['name' => 'Zé', 'active' => true]);
    }

    private function trecho(array $extra, int $odoSaida, int $odoEntrada, string $saidaEm, string $entradaEm)
    {
        $s = app(SaidaVeiculoService::class);
        $saida = $s->registrarSaida($this->veiculo->refresh(), array_merge([
            'finalidade' => 'cliente', 'motorista_id' => $this->motorista->id, 'motivo' => 'Viagem', 'saida_em' => $saidaEm, 'odometro' => $odoSaida,
        ], $extra), $this->u);

        return $s->registrarEntrada($saida, ['retorno_em' => $entradaEm, 'odometro' => $odoEntrada], $this->u);
    }

    private function percurso(string $nome = 'Empresa ↔ Topmixx', ?string $previsto = '40'): FrotaPercurso
    {
        return FrotaPercurso::create(['tenant_id' => $this->u->tenant_id, 'nome' => $nome, 'local_a_tipo' => 'empresa', 'local_b_tipo' => 'cliente', 'local_b_cliente_id' => $this->cliente->id, 'km_previsto' => $previsto]);
    }

    public function test_ida_para_o_cliente_e_volta_para_a_empresa_somam_no_mesmo_percurso(): void
    {
        $p = $this->percurso();

        // Trecho 1: empresa → cliente (percurso encontrado sozinho). O veículo passa a estar no cliente.
        $t1 = $this->trecho(['origem_tipo' => 'empresa', 'destino_tipo' => 'cliente', 'cliente_id' => $this->cliente->id], 1000, 1042, now()->subHours(6)->toDateTimeString(), now()->subHours(5)->toDateTimeString());
        $this->assertSame($p->id, $t1->percurso_id);
        $this->assertSame('cliente', $t1->destino_tipo);
        $this->assertSame('Cliente Topmixx', $t1->destino);
        $local = $this->veiculo->refresh()->localAtual();
        $this->assertSame(['cliente', $this->cliente->id, 'Cliente Topmixx'], [$local['tipo'], $local['cliente_id'], $local['rotulo']]);

        // Trecho 2: cliente → empresa (sentido contrário, mesmo percurso). De volta à empresa.
        $t2 = $this->trecho(['origem_tipo' => 'cliente', 'origem_cliente_id' => $this->cliente->id, 'destino_tipo' => 'empresa'], 1042, 1085, now()->subHours(3)->toDateTimeString(), now()->subHours(2)->toDateTimeString());
        $this->assertSame($p->id, $t2->percurso_id);
        $this->assertSame('empresa', $this->veiculo->refresh()->localAtual()['tipo']);

        $linha = app(KmRodadoService::class)->porPercurso()->firstWhere(fn ($l) => $l['percurso']->id === $p->id);
        $this->assertSame(2, $linha['viagens']);
        $this->assertSame(85, $linha['km_total']);
        $this->assertEquals(42.5, $linha['km_medio']);
        $this->assertEquals(6.3, round($linha['desvio_pct'], 1), 'média 42,5 contra 40 previstos = +6,3%');

        $v = app(KmRodadoService::class)->porVeiculo($this->veiculo->refresh());
        $this->assertSame(85, $v['total']);
        $this->assertSame(85, $v['em_percursos']);
        $this->assertSame(0, $v['fora_de_percurso']);
        $this->assertEquals(170.0, $v['custo'], 'custo estimado = km × custo por km');
    }

    public function test_km_marcado_fora_de_viagem_aparece_como_fora_de_percurso_e_queda_de_painel_nao_desconta(): void
    {
        $this->percurso();
        $this->trecho(['origem_tipo' => 'empresa', 'destino_tipo' => 'cliente', 'cliente_id' => $this->cliente->id], 1000, 1040, now()->subHours(5)->toDateTimeString(), now()->subHours(4)->toDateTimeString());

        FrotaLeituraOdometro::registrar($this->veiculo->refresh(), 1055, 'manual');                    // andou 15 km fora de viagem
        FrotaLeituraOdometro::registrar($this->veiculo->refresh(), 20, 'manual', null, 'Painel trocado'); // queda justificada
        FrotaLeituraOdometro::registrar($this->veiculo->refresh(), 50, 'manual');                      // +30 depois da troca

        $v = app(KmRodadoService::class)->porVeiculo($this->veiculo->refresh());

        $this->assertSame(40 + 15 + 30, $v['total'], 'a queda do painel não desconta');
        $this->assertSame(40, $v['em_percursos']);
        $this->assertSame(45, $v['fora_de_percurso']);
    }

    public function test_dois_percursos_possiveis_exigem_escolha_e_percurso_errado_e_recusado(): void
    {
        $a = $this->percurso('Rota curta');
        $b = $this->percurso('Rota pela rodovia', '55');
        $dados = ['finalidade' => 'cliente', 'motorista_id' => $this->motorista->id, 'motivo' => 'x', 'saida_em' => now()->subHour()->toDateTimeString(), 'odometro' => 1000, 'origem_tipo' => 'empresa', 'destino_tipo' => 'cliente', 'cliente_id' => $this->cliente->id];

        // Dois percursos ligam os mesmos locais: nada é escolhido sozinho.
        $s = app(SaidaVeiculoService::class)->registrarSaida($this->veiculo->refresh(), $dados, $this->u);
        $this->assertNull($s->percurso_id);
        app(SaidaVeiculoService::class)->registrarEntrada($s, ['retorno_em' => now()->toDateTimeString(), 'odometro' => 1040], $this->u);

        // Escolhendo, vale o escolhido.
        $s2 = app(SaidaVeiculoService::class)->registrarSaida($this->veiculo->refresh(), array_merge($dados, ['origem_tipo' => 'cliente', 'origem_cliente_id' => $this->cliente->id, 'destino_tipo' => 'empresa', 'cliente_id' => null, 'odometro' => 1040, 'saida_em' => now()->toDateTimeString(), 'percurso_id' => $b->id]), $this->u);
        $this->assertSame($b->id, $s2->percurso_id);
        app(SaidaVeiculoService::class)->registrarEntrada($s2, ['retorno_em' => now()->addMinute()->toDateTimeString(), 'odometro' => 1100], $this->u);

        // Percurso que não liga os locais do trecho é recusado.
        $outro = Client::create(['tenant_id' => $this->u->tenant_id, 'name' => 'Outro Cliente', 'email' => 'o-'.uniqid().'@t.com', 'password' => 'senha123']);
        try {
            app(SaidaVeiculoService::class)->registrarSaida($this->veiculo->refresh(), array_merge($dados, ['destino_tipo' => 'cliente', 'cliente_id' => $outro->id, 'odometro' => 1100, 'saida_em' => now()->addMinutes(2)->toDateTimeString(), 'percurso_id' => $a->id]), $this->u);
            $this->fail('devia recusar');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('não liga', collect($e->errors())->flatten()->first());
        }

        $this->assertNotNull($a);
    }

    public function test_saida_com_so_o_texto_do_destino_continua_funcionando(): void
    {
        $s = app(SaidaVeiculoService::class)->registrarSaida($this->veiculo->refresh(), ['finalidade' => 'cliente', 'motorista_id' => $this->motorista->id, 'destino' => 'Obra Norte', 'motivo' => 'x', 'saida_em' => now()->subHour()->toDateTimeString(), 'odometro' => 1000], $this->u);

        $this->assertSame(['empresa', 'outro', 'Empresa', 'Obra Norte'], [$s->origem_tipo, $s->destino_tipo, $s->origem, $s->destino]);
    }

    public function test_destino_cliente_sem_escolher_o_cliente_e_recusado(): void
    {
        try {
            app(SaidaVeiculoService::class)->registrarSaida($this->veiculo->refresh(), ['finalidade' => 'cliente', 'motorista_id' => $this->motorista->id, 'destino_tipo' => 'cliente', 'motivo' => 'x', 'saida_em' => now()->subHour()->toDateTimeString(), 'odometro' => 1000], $this->u);
            $this->fail('devia recusar');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Escolha o cliente', collect($e->errors())->flatten()->first());
        }
    }

    public function test_telas_km_rodado_no_app_no_painel_e_na_impressao_mostram_os_numeros(): void
    {
        $this->percurso();
        $this->trecho(['origem_tipo' => 'empresa', 'destino_tipo' => 'cliente', 'cliente_id' => $this->cliente->id], 1000, 1042, now()->subHours(5)->toDateTimeString(), now()->subHours(4)->toDateTimeString());
        $celular = ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1'];

        $this->withHeaders($celular)->get('/colaborador/logistica/km-rodado')->assertOk()->assertSee('KMK1A11')->assertSee('42 km')->assertSee('Empresa ↔ Topmixx');
        $this->withHeaders($celular)->get('/colaborador/logistica')->assertOk()->assertSee('Km rodado e percursos');

        $impresso = $this->get('/admin/print/km-rodado?periodo=mes')->assertOk()->getContent();
        $this->assertStringContainsString('KM RODADO DA FROTA', mb_strtoupper($impresso));
        $this->assertStringContainsString('Período: Mês atual', $impresso);
        $this->assertStringContainsString('42 km', $impresso);
        $this->assertStringContainsString('Oravel', $impresso); // rodapé padrão

        Livewire::test(KmRodadoFrota::class)->assertSee('KMK1A11')->set('periodo', 'tudo')->assertSee('42 km');
    }

    public function test_cadastro_de_percursos_lista_totais_e_nao_apaga_o_que_ja_recebeu_viagens(): void
    {
        $p = $this->percurso();
        $this->trecho(['origem_tipo' => 'empresa', 'destino_tipo' => 'cliente', 'cliente_id' => $this->cliente->id], 1000, 1042, now()->subHours(5)->toDateTimeString(), now()->subHours(4)->toDateTimeString());

        Livewire::test(ListFrotaPercursos::class)
            ->assertCanSeeTableRecords([$p])->assertSee('42 km')->assertSee('Empresa ↔ Cliente Topmixx')
            ->assertTableActionHidden('delete', $p);

        $this->assertSame(1, $p->viagens()->count());
    }

    public function test_cadastro_do_ativo_mostra_o_km_rodado_do_veiculo(): void
    {
        $this->percurso();
        $this->trecho(['origem_tipo' => 'empresa', 'destino_tipo' => 'cliente', 'cliente_id' => $this->cliente->id], 1000, 1042, now()->subHours(5)->toDateTimeString(), now()->subHours(4)->toDateTimeString());

        $html = view('filament.partials.asset-km-rodado', ['ativo' => $this->veiculo->refresh()])->render();

        $this->assertStringContainsString('Mês atual', $html);
        $this->assertStringContainsString('42 km', $html);
        $this->assertStringContainsString('R$ 84,00', $html); // 42 km × R$ 2,00 por km
    }
}
