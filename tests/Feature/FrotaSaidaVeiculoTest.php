<?php

namespace Tests\Feature;

use App\Filament\Resources\FrotaSaidaVeiculoResource\Pages\ListFrotaSaidasVeiculo;
use App\Livewire\SaidaVeiculoMobile;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaChecklist;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaSaidaVeiculo;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\PendenciasFrotaService;
use App\Services\Frota\SaidaVeiculoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Entrada e saída de veículos (Logística): quem levou, para quê, km, travas e retorno. */
class FrotaSaidaVeiculoTest extends TestCase
{
    use DatabaseTransactions;

    private SaidaVeiculoService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new SaidaVeiculoService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_saidas_veiculo']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant, array $extra = []): Asset
    {
        return Asset::create(array_merge(['tenant_id' => $tenant->id, 'name' => 'Carro '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000], $extra));
    }

    private function dados(array $extra = []): array
    {
        return array_merge(['finalidade' => 'visita_tecnica', 'condutor_nome' => 'João', 'destino' => 'Cliente X', 'motivo' => 'Visita ao cliente', 'odometro' => 10000], $extra);
    }

    private function erroEm(callable $fn, string $campo): void
    {
        try {
            $fn();
            $this->fail("Deveria recusar ({$campo}).");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
        }
    }

    public function test_saida_e_entrada_registram_km_rodado_e_leituras_de_odometro(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);

        $saida = $this->servico->registrarSaida($v, $this->dados());
        $this->assertTrue($saida->estaFora());
        $this->assertNotNull($v->saidaAberta());

        $this->servico->registrarEntrada($saida->fresh(), ['odometro' => 10120]);

        $saida = $saida->fresh();
        $this->assertFalse($saida->estaFora());
        $this->assertSame(120, $saida->kmRodado());
        $this->assertNull($v->saidaAberta());
        $this->assertSame(['saida_veiculo', 'entrada_veiculo'], FrotaLeituraOdometro::where('ativo_id', $v->id)->orderBy('lido_em')->orderBy('id')->pluck('origem')->all());
    }

    public function test_todas_as_finalidades_pedidas_existem(): void
    {
        $l = FrotaSaidaVeiculo::finalidadeLabels();
        foreach (['visita_tecnica', 'administrativo', 'diretoria', 'cliente', 'locacao', 'manutencao'] as $f) {
            $this->assertArrayHasKey($f, $l);
        }
    }

    public function test_veiculo_ja_fora_nao_sai_de_novo_e_volta_a_sair_depois_da_entrada(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $s = $this->servico->registrarSaida($v, $this->dados());

        $this->erroEm(fn () => $this->servico->registrarSaida($v, $this->dados()), 'ativo');
        $this->servico->registrarEntrada($s, ['odometro' => 10010]);
        $this->assertTrue($this->servico->registrarSaida($v->fresh(), $this->dados(['odometro' => 10010]))->estaFora());
        $this->erroEm(fn () => $this->servico->registrarEntrada($s->fresh(), ['odometro' => 10020]), 'saida');
    }

    public function test_checklist_bloqueado_impede_a_saida_e_liberado_permite(): void
    {
        [$tenant, $admin] = $this->cliente();
        $v = $this->veiculo($tenant);
        $c = FrotaChecklist::create(['tenant_id' => $tenant->id, 'ativo_id' => $v->id, 'tipo' => 'saida', 'odometro' => 10000, 'situacao' => FrotaChecklist::BLOQUEADO, 'concluido_em' => now()]);

        $this->erroEm(fn () => $this->servico->registrarSaida($v, $this->dados()), 'ativo');
        $this->assertSame(0, FrotaSaidaVeiculo::count());

        $c->update(['situacao' => FrotaChecklist::LIBERADO, 'liberado_por' => $admin->id, 'liberado_em' => now(), 'motivo_liberacao' => 'ok']);
        $this->assertTrue($this->servico->registrarSaida($v->fresh(), $this->dados())->estaFora());
    }

    public function test_cnh_vencida_e_falta_de_condutor_ou_finalidade_sao_recusadas(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $vencido = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'Pedro', 'cnh_expiry_date' => now()->subDay(), 'active' => true]);
        $ok = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'Ana', 'cnh_expiry_date' => now()->addYear(), 'active' => true]);

        $this->erroEm(fn () => $this->servico->registrarSaida($v, $this->dados(['condutor_nome' => null, 'motorista_id' => $vencido->id])), 'motorista_id');
        $this->erroEm(fn () => $this->servico->registrarSaida($v, $this->dados(['condutor_nome' => null])), 'motorista_id');
        $this->erroEm(fn () => $this->servico->registrarSaida($v, $this->dados(['finalidade' => 'passeio'])), 'finalidade');
        $this->assertSame('Ana', $this->servico->registrarSaida($v, $this->dados(['condutor_nome' => null, 'motorista_id' => $ok->id]))->condutor());
    }

    public function test_odometro_menor_so_com_justificativa_e_nada_grava_se_recusar(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);

        $this->erroEm(fn () => $this->servico->registrarSaida($v, $this->dados(['odometro' => 500])), 'odometro');
        $this->assertSame(0, FrotaSaidaVeiculo::count());

        $s = $this->servico->registrarSaida($v, $this->dados());
        $this->erroEm(fn () => $this->servico->registrarEntrada($s, ['odometro' => 9000]), 'odometro');
        $this->assertTrue($s->fresh()->estaFora());
    }

    public function test_destino_motivo_e_data_hora_sao_registrados_e_validados(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);

        $this->erroEm(fn () => $this->servico->registrarSaida($v, $this->dados(['destino' => ' '])), 'destino');
        $this->erroEm(fn () => $this->servico->registrarSaida($v, $this->dados(['motivo' => ''])), 'motivo');
        $this->erroEm(fn () => $this->servico->registrarSaida($v, $this->dados(['saida_em' => now()->addDay()->toDateTimeString()])), 'saida_em');

        $quando = now()->subHours(3)->startOfMinute();
        $s = $this->servico->registrarSaida($v, $this->dados(['saida_em' => $quando->toDateTimeString()]));
        $this->assertTrue($s->fresh()->saida_em->equalTo($quando));
        $this->assertSame('Visita ao cliente', $s->fresh()->motivo);

        $this->erroEm(fn () => $this->servico->registrarEntrada($s, ['odometro' => 10010, 'retorno_em' => $quando->copy()->subHour()->toDateTimeString()]), 'retorno_em');
        $volta = now()->subHour()->startOfMinute();
        $this->servico->registrarEntrada($s->fresh(), ['odometro' => 10010, 'retorno_em' => $volta->toDateTimeString()]);
        $this->assertTrue($s->fresh()->retorno_em->equalTo($volta));
    }

    public function test_so_veiculo_tem_saida(): void
    {
        [$tenant] = $this->cliente();
        $maquina = $this->veiculo($tenant, ['grupo' => Asset::GRUPO_MAQUINA]);

        $this->erroEm(fn () => $this->servico->registrarSaida($maquina, $this->dados()), 'ativo');
    }

    public function test_fora_ha_mais_de_24h_vira_pendencia(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $s = $this->servico->registrarSaida($v, $this->dados());
        $this->assertSame(0, (new PendenciasFrotaService)->doVeiculo($v)->where('categoria', 'saida')->count());

        $s->update(['saida_em' => now()->subHours(30)]);
        $this->assertStringContainsString('fora há 30 horas', (new PendenciasFrotaService)->doVeiculo($v->fresh())->firstWhere('categoria', 'saida')['mensagem']);
    }

    public function test_um_cliente_nao_ve_a_saida_do_outro(): void
    {
        [$a, $adminA] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $this->servico->registrarSaida($this->veiculo($a), $this->dados());

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaSaidaVeiculo::count());
        $this->actingAs($adminA);
        $this->assertSame(1, FrotaSaidaVeiculo::count());
    }

    public function test_tela_registra_saida_e_entrada(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);

        Livewire::test(ListFrotaSaidasVeiculo::class)
            ->callTableAction('registrar_saida', data: ['ativo_id' => $v->id, 'finalidade' => 'diretoria', 'condutor_nome' => 'Diretor', 'destino' => 'Reunião', 'motivo' => 'Reunião externa', 'odometro' => 10000])
            ->assertHasNoTableActionErrors();
        $saida = FrotaSaidaVeiculo::where('ativo_id', $v->id)->sole();
        $this->assertSame('diretoria', $saida->finalidade);

        Livewire::test(ListFrotaSaidasVeiculo::class)
            ->callTableAction('registrar_entrada', $saida, data: ['odometro' => 10050]);
        $this->assertFalse($saida->fresh()->estaFora());
    }

    public function test_celular_registra_saida_mostra_erro_e_registra_entrada(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);

        $tela = Livewire::test(SaidaVeiculoMobile::class)
            ->assertSee($v->placa)->assertSee('NA BASE')
            ->call('escolher', $v->id)
            ->set('condutorNome', 'Maria')->set('destino', 'Obra Y')->set('motivo', '')
            ->call('saida')
            ->assertSet('ativoId', $v->id);
        $this->assertStringContainsString('motivo', mb_strtolower($tela->get('erro')));

        $tela->set('motivo', 'Entrega de peça')->call('saida')->assertSet('mensagem', 'Saída registrada.')->assertSet('ativoId', null);
        $this->assertSame('Obra Y', FrotaSaidaVeiculo::where('ativo_id', $v->id)->value('destino'));

        Livewire::test(SaidaVeiculoMobile::class, ['assetId' => $v->id])
            ->assertSee('Entrega de peça')
            ->set('odometro', '10040')->call('entrada')->assertSet('mensagem', 'Entrada registrada.');
        $this->assertSame(40, FrotaSaidaVeiculo::where('ativo_id', $v->id)->first()->kmRodado());
    }

    public function test_celular_nao_abre_para_quem_nao_tem_permissao_e_so_para_veiculo(): void
    {
        [$tenant, $admin] = $this->cliente();
        $semPermissao = User::create(['name' => 'Op', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $this->actingAs($semPermissao);
        Livewire::test(SaidaVeiculoMobile::class)->assertForbidden();

        $this->actingAs($admin);
        $maquina = $this->veiculo($tenant, ['grupo' => Asset::GRUPO_MAQUINA]);
        Livewire::test(SaidaVeiculoMobile::class)->call('escolher', $maquina->id)->assertNotFound();
    }
}
