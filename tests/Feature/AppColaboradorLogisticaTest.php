<?php

namespace Tests\Feature;

use App\Livewire\AbastecimentoMobile;
use App\Livewire\MarcarKmMobile;
use App\Livewire\MotoristaVeiculoMobile;
use App\Livewire\SaidaVeiculoMobile;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaSaidaVeiculo;
use App\Models\FrotaVinculoMotorista;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\SaidaVeiculoService;
use App\Services\Frota\VinculoMotoristaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Logística no app do colaborador: entrada e saída de veículos com motivo e equipamento levado. */
class AppColaboradorLogisticaTest extends TestCase
{
    use DatabaseTransactions;

    private const CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private function admin(): User
    {
        $plan = Plan::create(['name' => 'Plano Log '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets', 'tabela_frota_saidas_veiculo', 'tabela_frota_abastecimentos', 'tabela_frota_vinculos_motorista', 'modulo_app_colaborador']]);
        $tenant = Tenant::create(['name' => 'Locadora Log', 'slug' => 'log-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $user = User::create(['name' => 'Gestor', 'email' => 'log-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $user->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return $user->refresh();
    }

    private function veiculo(User $u, string $placa = 'ABC1D23'): Asset
    {
        return Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Caminhão Munck '.$placa, 'placa' => $placa, 'grupo' => Asset::GRUPO_VEICULO, 'status' => 'disponivel', 'odometro_atual' => 1000]);
    }

    private function equipamento(User $u, string $pat = 'PAT-EQ1'): Asset
    {
        return Asset::create(['tenant_id' => $u->tenant_id, 'name' => 'Empilhadeira '.$pat, 'patrimonio' => $pat, 'status' => 'disponivel']);
    }

    private function dadosSaida(?string $equipamentoId, array $extra = []): array
    {
        $motorista = FleetDriver::create(['name' => 'Zé Motorista', 'active' => true]);

        return array_merge(['finalidade' => 'visita_tecnica', 'motorista_id' => $motorista->id, 'destino' => 'Obra Centro', 'motivo' => 'Levar empilhadeira ao cliente', 'saida_em' => now()->toDateTimeString(), 'odometro' => 1000, 'equipamento_id' => $equipamentoId], $extra);
    }

    public function test_saida_guarda_motivo_e_o_equipamento_levado_e_a_entrada_fecha(): void
    {
        $u = $this->admin();
        $this->actingAs($u);
        $v = $this->veiculo($u);
        $eq = $this->equipamento($u);

        $saida = app(SaidaVeiculoService::class)->registrarSaida($v, $this->dadosSaida($eq->id), $u);

        $this->assertSame($eq->id, $saida->equipamento_id);
        $this->assertSame('Levar empilhadeira ao cliente', $saida->motivo);
        $this->assertSame($eq->id, $saida->equipamento->id);

        app(SaidaVeiculoService::class)->registrarEntrada($saida, ['retorno_em' => now()->addMinute()->toDateTimeString(), 'odometro' => 1040], $u);
        $this->assertFalse($saida->refresh()->estaFora());
    }

    public function test_saida_sem_equipamento_continua_valida(): void
    {
        $u = $this->admin();
        $this->actingAs($u);

        $saida = app(SaidaVeiculoService::class)->registrarSaida($this->veiculo($u), $this->dadosSaida(null), $u);

        $this->assertNull($saida->equipamento_id);
    }

    public function test_equipamento_nao_pode_ser_o_proprio_veiculo_nem_estar_em_outra_saida_aberta(): void
    {
        $u = $this->admin();
        $this->actingAs($u);
        $v1 = $this->veiculo($u, 'AAA1A11');
        $v2 = $this->veiculo($u, 'BBB2B22');
        $eq = $this->equipamento($u);
        $servico = app(SaidaVeiculoService::class);

        $erro = fn (callable $f) => collect(rescue(fn () => $f() && null, fn (ValidationException $e) => $e->errors(), false))->flatten()->first();

        $this->assertStringContainsString('si mesmo', (string) $erro(fn () => $servico->registrarSaida($v1, $this->dadosSaida($v1->id), $u)));

        $servico->registrarSaida($v1, $this->dadosSaida($eq->id), $u);
        $mensagem = (string) $erro(fn () => $servico->registrarSaida($v2, $this->dadosSaida($eq->id), $u));
        $this->assertStringContainsString('já está sendo levado pelo veículo AAA1A11', $mensagem);

        $this->assertSame(1, FrotaSaidaVeiculo::fora()->where('equipamento_id', $eq->id)->count());
    }

    public function test_tela_do_celular_busca_e_escolhe_o_equipamento_pelo_patrimonio_e_registra(): void
    {
        $u = $this->admin();
        $this->actingAs($u);
        $v = $this->veiculo($u);
        $eq = $this->equipamento($u, 'PAT-9001');
        $outroVeiculo = $this->veiculo($u, 'ZZZ9Z99');
        $motorista = FleetDriver::create(['name' => 'Maria Motorista', 'active' => true]);

        Livewire::test(SaidaVeiculoMobile::class)
            ->call('escolher', $v->id)
            ->set('buscaEquipamento', 'PAT-9001')
            ->assertSee('Empilhadeira PAT-9001')
            ->call('escolherEquipamento', $eq->id)
            ->set('motoristaId', $motorista->id)->set('destinoTipo', 'outro')->set('destino', 'Obra Norte')->set('motivo', 'Entrega')->set('odometro', '1000')
            ->call('saida')
            ->assertSet('erro', null)->assertSet('mensagem', 'Saída registrada.');

        $this->assertSame($eq->id, FrotaSaidaVeiculo::fora()->where('ativo_id', $v->id)->first()->equipamento_id);

        // A busca nunca oferece outro veículo como "equipamento".
        Livewire::test(SaidaVeiculoMobile::class)->call('escolher', $outroVeiculo->id)->set('buscaEquipamento', 'ZZZ9Z99')->assertSee('Nenhum equipamento encontrado');
        $this->assertNotNull($outroVeiculo);
    }

    public function test_app_mostra_logistica_no_inicio_e_abre_a_tela_com_volta_para_o_app(): void
    {
        $u = $this->admin();
        $v = $this->veiculo($u);

        $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($u)->get('/colaborador')->assertOk()->assertSee('Logística')->assertSee('Entrada e saída de veículos');

        $html = $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($u)->get('/colaborador/logistica/saida')->assertOk()->assertSee('ENTRADA E SAÍDA')->getContent();
        $this->assertStringContainsString('href="'.route('colab.logistica').'"', $html);
        $this->assertStringNotContainsString('href="/admin"', $html);

        // O dossiê do veículo, aberto no app, leva direto para registrar a saída dele.
        $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($u)->get('/colaborador/dossie/'.$v->id)->assertOk()->assertSee(route('colab.logistica.saida', ['assetId' => $v->id]), false)->assertSee(route('colab.logistica.abastecimento', ['assetId' => $v->id]), false)->assertSee(route('colab.logistica.km', ['assetId' => $v->id]), false)->assertSee(route('colab.logistica.motorista', ['assetId' => $v->id]), false);
    }

    public function test_inicio_da_logistica_lista_as_funcoes_e_avisa_de_documento_vencido(): void
    {
        $u = $this->admin();
        $v = $this->veiculo($u);
        $v->forceFill(['ipva_vencimento' => now()->subDays(3)])->save();

        $r = $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($u)->get('/colaborador/logistica')->assertOk();
        foreach (['Entrada e saída de veículos', 'Abastecimento', 'Marcar km', 'Motorista do veículo', 'Vencimentos da documentação', '1 veículo(s) com documento vencido'] as $texto) {
            $r->assertSee($texto);
        }
    }

    public function test_abastecimento_registra_e_marca_o_km_do_veiculo(): void
    {
        $u = $this->admin();
        $this->actingAs($u);
        $v = $this->veiculo($u);

        Livewire::test(AbastecimentoMobile::class, ['assetId' => $v->id])
            ->set('odometro', '1200')->set('litros', '55,5')->set('valorTotal', '333')->set('posto', 'Posto Shell')
            ->call('registrar')->assertSet('erro', null)->assertSee('Abastecimento registrado');

        $a = FrotaAbastecimento::where('ativo_id', $v->id)->first();
        $this->assertNotNull($a);
        $this->assertSame(1200, $a->odometro);
        $this->assertSame(1200, (int) $v->refresh()->odometro_atual);

        // Sem litros, o serviço recusa e mostra a mensagem.
        Livewire::test(AbastecimentoMobile::class, ['assetId' => $v->id])->set('odometro', '1300')->call('registrar')->assertSet('erro', 'Informe os litros abastecidos (maior que zero).');
    }

    public function test_motorista_do_veiculo_inclui_substitui_e_encerra(): void
    {
        $u = $this->admin();
        $this->actingAs($u);
        $v = $this->veiculo($u);
        $ana = FleetDriver::create(['name' => 'Ana', 'active' => true]);
        $beto = FleetDriver::create(['name' => 'Beto', 'active' => true]);
        $servico = VinculoMotoristaService::class;

        Livewire::test(MotoristaVeiculoMobile::class, ['assetId' => $v->id])->set('motoristaId', $ana->id)->call('atribuir')->assertSee('Ana é o motorista do veículo');
        $this->assertSame($ana->id, $servico::titular($v)->motorista_id);

        Livewire::test(MotoristaVeiculoMobile::class, ['assetId' => $v->id])->set('motoristaId', $beto->id)->call('atribuir')->assertSee('Motorista substituído: agora é Beto');
        $this->assertSame($beto->id, $servico::titular($v)->motorista_id);
        $this->assertNotNull(FrotaVinculoMotorista::where('motorista_id', $ana->id)->first()->fim, 'o vínculo anterior foi encerrado');

        Livewire::test(MotoristaVeiculoMobile::class, ['assetId' => $v->id])->call('encerrar')->assertSee('ficou sem motorista titular');
        $this->assertNull($servico::titular($v));
    }

    public function test_marcar_km_atualiza_o_veiculo_e_exige_justificativa_se_for_menor(): void
    {
        $u = $this->admin();
        $this->actingAs($u);
        $v = $this->veiculo($u);

        Livewire::test(MarcarKmMobile::class, ['assetId' => $v->id])->set('odometro', '1150')->call('marcar')->assertSee('Km marcado: 1.150 km');
        $this->assertSame(1150, (int) $v->refresh()->odometro_atual);

        Livewire::test(MarcarKmMobile::class, ['assetId' => $v->id])->set('odometro', '900')->call('marcar')->assertSet('mensagem', null)->assertSet('erro', fn ($e) => str_contains((string) $e, 'menor que o último'));
        $this->assertSame(1150, (int) $v->refresh()->odometro_atual);

        Livewire::test(MarcarKmMobile::class, ['assetId' => $v->id])->set('odometro', '900')->set('justificativa', 'Painel trocado')->call('marcar')->assertSee('Km marcado: 900 km');
    }

    public function test_vencimentos_separa_vencido_atencao_e_esconde_tacografo_de_veiculo_leve(): void
    {
        $u = $this->admin();
        $leve = $this->veiculo($u, 'LEV1E23');
        $leve->forceFill(['seguro_vencimento' => now()->subDays(2), 'tacografo_vencimento' => now()->subDays(50), 'veiculo_pesado' => false])->save();
        $pesado = $this->veiculo($u, 'PES4A56');
        $pesado->forceFill(['licenciamento_vencimento' => now()->addDays(10), 'tacografo_vencimento' => now()->addDays(100), 'veiculo_pesado' => true])->save();
        $emDia = $this->veiculo($u, 'OKK7B89');
        $emDia->forceFill(['ipva_vencimento' => now()->addDays(200)])->save();

        $r = $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($u)->get('/colaborador/logistica/vencimentos')->assertOk();
        $r->assertSee('LEV1E23')->assertSee('Seguro')->assertSee('venceu há 2 dias')->assertSee('PES4A56')->assertSee('faltam 10 dias')->assertDontSee('OKK7B89')->assertDontSee('Tacógrafo: '.now()->subDays(50)->format('d/m/Y'));

        $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($u)->get('/colaborador/logistica/vencimentos?aba=todos')->assertOk()->assertSee('OKK7B89')->assertSee('Todos (3)');
        $this->withHeaders(['User-Agent' => self::CELULAR])->actingAs($u)->get('/colaborador/logistica/vencimentos?aba=todos&q=PES4A56')->assertOk()->assertSee('PES4A56')->assertDontSee('LEV1E23');
    }
}
