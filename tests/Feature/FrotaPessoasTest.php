<?php

namespace Tests\Feature;

use App\Filament\Pages\FrotaRankingMotoristas;
use App\Filament\Resources\FrotaChaveResource\Pages\ListFrotaChaves;
use App\Filament\Resources\FrotaVinculoMotoristaResource\Pages\ListFrotaVinculosMotorista;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaChave;
use App\Models\FrotaEntregaChave;
use App\Models\FrotaVinculoMotorista;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\AbastecimentoService;
use App\Services\Frota\ChaveService;
use App\Services\Frota\IndicadoresMotoristaService;
use App\Services\Frota\MultaService;
use App\Services\Frota\PendenciasFrotaService;
use App\Services\Frota\SaidaVeiculoService;
use App\Services\Frota\SinistroService;
use App\Services\Frota\VinculoMotoristaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 14: motorista titular, chaves (entrega/devolução) e ranking de motoristas. */
class FrotaPessoasTest extends TestCase
{
    use DatabaseTransactions;

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_vinculos_motorista', 'tabela_frota_chaves', 'tabela_frota_multas']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant): Asset
    {
        return Asset::create(['tenant_id' => $tenant->id, 'name' => 'Carro '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000]);
    }

    private function motorista(Tenant $tenant, string $nome = 'João', array $extra = []): FleetDriver
    {
        return FleetDriver::create(array_merge(['tenant_id' => $tenant->id, 'name' => $nome, 'cnh_expiry_date' => now()->addYear(), 'active' => true], $extra));
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

    // ---- motorista titular ----

    public function test_atribuir_titular_encerra_o_anterior_e_so_um_vigente(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $a = $this->motorista($tenant, 'Ana');
        $b = $this->motorista($tenant, 'Beto');
        $servico = new VinculoMotoristaService;

        $servico->atribuir($v, $a->id, now()->subDays(10)->toDateString());
        $this->assertSame($a->id, VinculoMotoristaService::titular($v)->motorista_id);
        $this->erroEm(fn () => $servico->atribuir($v, $a->id), 'motorista_id');

        $servico->atribuir($v, $b->id, now()->subDays(2)->toDateString());
        $this->assertSame($b->id, VinculoMotoristaService::titular($v)->motorista_id);
        $this->assertSame(1, FrotaVinculoMotorista::where('ativo_id', $v->id)->whereNull('fim')->count());
        $this->assertSame(now()->subDays(2)->toDateString(), FrotaVinculoMotorista::where('motorista_id', $a->id)->value('fim')->toDateString());
    }

    public function test_titular_recusa_cnh_vencida_inativo_data_futura_ou_anterior_e_nao_veiculo(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $servico = new VinculoMotoristaService;
        $ok = $this->motorista($tenant, 'Ok');
        $servico->atribuir($v, $ok->id, now()->subDays(5)->toDateString());

        $this->erroEm(fn () => $servico->atribuir($v, $this->motorista($tenant, 'Vencido', ['cnh_expiry_date' => now()->subDay()])->id), 'motorista_id');
        $this->erroEm(fn () => $servico->atribuir($v, $this->motorista($tenant, 'Inativo', ['active' => false])->id), 'motorista_id');
        $outro = $this->motorista($tenant, 'Outro');
        $this->erroEm(fn () => $servico->atribuir($v, $outro->id, now()->addDay()->toDateString()), 'inicio');
        $this->erroEm(fn () => $servico->atribuir($v, $outro->id, now()->subDays(20)->toDateString()), 'inicio');
        $this->erroEm(fn () => $servico->atribuir($this->veiculo($tenant)->forceFill(['grupo' => Asset::GRUPO_MAQUINA]), $outro->id), 'ativo');
    }

    public function test_encerrar_vinculo_valida_datas_e_libera_o_veiculo_sem_titular(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $servico = new VinculoMotoristaService;
        $vinculo = $servico->atribuir($v, $this->motorista($tenant)->id, now()->subDays(5)->toDateString());

        $this->erroEm(fn () => $servico->encerrar($vinculo, now()->subDays(9)->toDateString()), 'fim');
        $this->erroEm(fn () => $servico->encerrar($vinculo, now()->addDay()->toDateString()), 'fim');
        $servico->encerrar($vinculo);
        $this->assertNull(VinculoMotoristaService::titular($v));
        $this->erroEm(fn () => $servico->encerrar($vinculo->fresh()), 'fim');
    }

    // ---- chaves ----

    public function test_chave_so_fica_com_uma_pessoa_por_vez_e_devolucao_valida_datas(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $servico = new ChaveService;
        $chave = $servico->criar($v, 'Chave principal');
        $this->erroEm(fn () => $servico->criar($v, 'chave principal'), 'identificacao');

        $e = $servico->entregar($chave, ['responsavel_nome' => 'Mecânico Zé', 'motivo' => 'Revisão', 'entregue_em' => now()->subDays(2)->toDateTimeString()]);
        $this->assertSame('Mecânico Zé', $e->responsavel());
        $this->erroEm(fn () => $servico->entregar($chave, ['motorista_id' => $this->motorista($tenant)->id, 'motivo' => 'X']), 'chave');
        $this->erroEm(fn () => $servico->desativar($chave), 'chave');
        $this->erroEm(fn () => $servico->devolver($e, now()->subDays(5)->toDateTimeString()), 'devolvida_em');
        $this->erroEm(fn () => $servico->devolver($e, now()->addDay()->toDateTimeString()), 'devolvida_em');

        $servico->devolver($e, null, 'Tudo certo');
        $this->assertNull($chave->fresh()->entregaAberta());
        $this->erroEm(fn () => $servico->devolver($e->fresh()), 'devolvida_em');
        $this->assertSame('João', $servico->entregar($chave->fresh(), ['motorista_id' => $this->motorista($tenant)->id, 'motivo' => 'Viagem'])->responsavel());
    }

    public function test_entregar_valida_responsavel_motivo_e_data(): void
    {
        [$tenant] = $this->cliente();
        $chave = (new ChaveService)->criar($this->veiculo($tenant), 'Reserva');
        $servico = new ChaveService;

        $this->erroEm(fn () => $servico->entregar($chave, ['motivo' => 'X']), 'motorista_id');
        $this->erroEm(fn () => $servico->entregar($chave, ['responsavel_nome' => 'Fulano', 'motivo' => ' ']), 'motivo');
        $this->erroEm(fn () => $servico->entregar($chave, ['responsavel_nome' => 'Fulano', 'motivo' => 'X', 'entregue_em' => now()->addDay()->toDateTimeString()]), 'entregue_em');
        $this->assertSame(0, FrotaEntregaChave::count());
    }

    public function test_chave_fora_ha_7_dias_ou_mais_vira_pendencia(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $servico = new ChaveService;
        $chave = $servico->criar($v, 'Principal');
        $servico->entregar($chave, ['responsavel_nome' => 'Zé', 'motivo' => 'Oficina', 'entregue_em' => now()->subDays(3)->toDateTimeString()]);
        $this->assertSame(0, (new PendenciasFrotaService)->doVeiculo($v)->where('categoria', 'chave')->count());

        FrotaEntregaChave::query()->update(['entregue_em' => now()->subDays(9)]);
        $p = (new PendenciasFrotaService)->doVeiculo($v)->firstWhere('categoria', 'chave');
        $this->assertStringContainsString('está com Zé há 9 dia(s)', $p['mensagem']);
    }

    // ---- ranking ----

    public function test_indicadores_somam_saidas_km_multas_sinistros_e_consumo_do_motorista(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $m = $this->motorista($tenant);
        $saidas = new SaidaVeiculoService;
        $s = $saidas->registrarSaida($v, ['finalidade' => 'cliente', 'motorista_id' => $m->id, 'destino' => 'X', 'motivo' => 'Y', 'odometro' => 10000, 'saida_em' => now()->subDays(3)->toDateTimeString()]);
        $saidas->registrarEntrada($s, ['odometro' => 10400, 'retorno_em' => now()->subDays(2)->toDateTimeString()]);
        (new MultaService)->registrar($v, ['numero_auto' => 'M1', 'infracao_em' => now()->subDays(2)->subHours(3)->toDateTimeString(), 'descricao' => 'x', 'gravidade' => 'grave', 'valor' => 100, 'vencimento' => now()->addMonth()->toDateString()]);
        (new SinistroService)->registrar($v, ['tipo' => 'avaria', 'ocorrido_em' => now()->subDays(2)->subHours(4)->toDateTimeString(), 'descricao' => 'x', 'culpa' => 'propria']);
        $abast = new AbastecimentoService;
        $abast->registrar($v->fresh(), ['combustivel' => 'diesel_s10', 'litros' => 50, 'valor_total' => 300, 'odometro' => 10400, 'motorista_id' => $m->id, 'abastecido_em' => now()->subDays(2)->subHours(2)->toDateTimeString()]);
        $abast->registrar($v->fresh(), ['combustivel' => 'diesel_s10', 'litros' => 100, 'valor_total' => 600, 'odometro' => 10900, 'motorista_id' => $m->id, 'abastecido_em' => now()->subDay()->toDateTimeString()]);

        $r = (new IndicadoresMotoristaService)->motorista($m, 3);

        $this->assertSame(1, $r['saidas']);
        $this->assertSame(400, $r['km']);
        $this->assertSame(1, $r['multas']);
        $this->assertSame(5, $r['pontos']);
        $this->assertSame(1, $r['sinistros']);
        $this->assertSame(1, $r['sinistros_culpa_propria']);
        $this->assertSame(5.0, $r['consumo_km_l']);
        $this->assertSame(5.0, $r['ocorrencias_por_mil_km']);   // 2 ocorrências em 400 km
    }

    public function test_um_cliente_nao_ve_o_do_outro_e_as_telas_funcionam(): void
    {
        [$a, $adminA] = $this->cliente();
        $vA = $this->veiculo($a);
        $mA = $this->motorista($a, 'Ana');
        [$b, $adminB] = $this->cliente();

        $this->actingAs($adminA);
        Livewire::test(ListFrotaVinculosMotorista::class)
            ->callTableAction('atribuir', data: ['ativo_id' => $vA->id, 'motorista_id' => $mA->id, 'inicio' => now()->toDateString()])
            ->assertHasNoTableActionErrors();
        Livewire::test(ListFrotaChaves::class)
            ->callTableAction('nova_chave', data: ['ativo_id' => $vA->id, 'identificacao' => 'Principal']);
        $chave = FrotaChave::where('identificacao', 'Principal')->sole();
        Livewire::test(ListFrotaChaves::class)
            ->callTableAction('entregar', $chave, data: ['motorista_id' => $mA->id, 'motivo' => 'Viagem', 'entregue_em' => now()->toDateTimeString()])
            ->assertSee('Com Ana');
        Livewire::test(ListFrotaChaves::class)->callTableAction('devolver', $chave, data: ['devolvida_em' => now()->toDateTimeString()]);
        $this->assertNull($chave->fresh()->entregaAberta());
        Livewire::test(FrotaRankingMotoristas::class)->assertSee('Nenhum motorista com saída');

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaVinculoMotorista::count());
        $this->assertSame(0, FrotaChave::count());
        $this->assertSame(0, FrotaEntregaChave::count());
    }
}
