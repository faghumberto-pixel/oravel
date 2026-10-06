<?php

namespace Tests\Feature;

use App\Filament\Resources\FrotaSinistroResource\Pages\CreateFrotaSinistro;
use App\Filament\Resources\FrotaSinistroResource\Pages\ListFrotaSinistros;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaSinistro;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\PendenciasFrotaService;
use App\Services\Frota\SaidaVeiculoService;
use App\Services\Frota\SinistroService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 8: sinistros e ocorrências (B.O., orçamento, dias parado, OS, trava de saída). */
class FrotaSinistroTest extends TestCase
{
    use DatabaseTransactions;

    private SinistroService $servico;

    private FleetDriver $motorista;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new SinistroService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_sinistros', 'tabela_frota_saidas_veiculo', 'tabela_maintenance_orders']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        $this->motorista = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'João', 'cnh_expiry_date' => now()->addYear(), 'active' => true]);

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant): Asset
    {
        return Asset::create(['tenant_id' => $tenant->id, 'name' => 'Carro '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000]);
    }

    private function dados(array $extra = []): array
    {
        return array_merge(['tipo' => 'colisao', 'ocorrido_em' => now()->subDays(2)->toDateTimeString(), 'descricao' => 'Batida traseira no semáforo'], $extra);
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

    public function test_registrar_valida_e_comeca_aberto(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);

        $this->erroEm(fn () => $this->servico->registrar($v, $this->dados(['tipo' => 'x'])), 'tipo');
        $this->erroEm(fn () => $this->servico->registrar($v, $this->dados(['descricao' => ' '])), 'descricao');
        $this->erroEm(fn () => $this->servico->registrar($v, $this->dados(['ocorrido_em' => now()->addDay()->toDateTimeString()])), 'ocorrido_em');
        $this->erroEm(fn () => $this->servico->registrar($this->veiculo($tenant)->forceFill(['grupo' => Asset::GRUPO_MAQUINA]), $this->dados()), 'ativo');

        $s = $this->servico->registrar($v, $this->dados());
        $this->assertSame(FrotaSinistro::ABERTO, $s->situacao);
        $this->assertFalse($s->parado());
        $this->assertNull($s->diasParado());
    }

    public function test_condutor_e_sugerido_pela_saida_do_veiculo(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        (new SaidaVeiculoService)->registrarSaida($v, ['finalidade' => 'cliente', 'motorista_id' => $this->motorista->id, 'destino' => 'X', 'motivo' => 'Y',
            'odometro' => 10000, 'saida_em' => now()->subDays(3)->toDateTimeString()]);

        $s = $this->servico->registrar($v, $this->dados());

        $this->assertSame($this->motorista->id, $s->motorista_id);
        $this->assertNotNull($s->saida_veiculo_id);
    }

    public function test_dias_parado_contam_ate_voltar_a_rodar_e_encerrar_exige_isso(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $s = $this->servico->registrar($v, $this->dados(['veiculo_parado' => true, 'ocorrido_em' => now()->subDays(10)->toDateTimeString()]));

        $this->assertTrue($s->parado());
        $this->assertSame(10, $s->diasParado());
        $this->erroEm(fn () => $this->servico->encerrar($s), 'veiculo_parado');
        $this->erroEm(fn () => $this->servico->voltouARodar($s, now()->subDays(20)->toDateTimeString()), 'voltou_a_rodar_em');

        $this->servico->voltouARodar($s, now()->subDays(4)->toDateTimeString());
        $s = $s->fresh();
        $this->assertFalse($s->parado());
        $this->assertSame(6, $s->diasParado());
        $this->servico->encerrar($s);
        $this->assertSame(FrotaSinistro::ENCERRADO, $s->fresh()->situacao);
        $this->erroEm(fn () => $this->servico->cancelar($s->fresh(), 'x'), 'situacao');
    }

    public function test_veiculo_parado_por_sinistro_nao_sai_e_volta_a_sair_depois(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $s = $this->servico->registrar($v, $this->dados(['veiculo_parado' => true]));
        $saida = ['finalidade' => 'manutencao', 'motorista_id' => $this->motorista->id, 'destino' => 'Oficina', 'motivo' => 'Reparo', 'odometro' => 10000];

        $this->erroEm(fn () => (new SaidaVeiculoService)->registrarSaida($v, $saida), 'ativo');
        $this->servico->voltouARodar($s);
        $this->assertTrue((new SaidaVeiculoService)->registrarSaida($v->fresh(), $saida)->estaFora());
    }

    public function test_gerar_os_abre_uma_so_e_orcamento_muda_a_situacao(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $s = $this->servico->registrar($v, $this->dados());

        $this->erroEm(fn () => $this->servico->registrarOrcamento($s, 0), 'valor_orcamento');
        $this->servico->registrarOrcamento($s, '4500,50', 1500);
        $this->assertSame(FrotaSinistro::EM_ORCAMENTO, $s->fresh()->situacao);
        $this->assertSame('4500.50', $s->fresh()->valor_orcamento);

        $os = $this->servico->gerarOs($s->fresh());
        $this->assertSame($v->id, $os->asset_id);
        $this->assertSame(FrotaSinistro::EM_REPARO, $s->fresh()->situacao);
        $this->assertSame($os->id, $s->fresh()->ordem_servico_id);
        $this->erroEm(fn () => $this->servico->gerarOs($s->fresh()), 'ordem_servico_id');
        $this->assertSame(1, MaintenanceOrder::where('asset_id', $v->id)->count());
    }

    public function test_cancelar_exige_motivo_e_cancelado_nao_trava_a_saida(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $s = $this->servico->registrar($v, $this->dados(['veiculo_parado' => true]));

        $this->erroEm(fn () => $this->servico->cancelar($s, ' '), 'motivo');
        $this->servico->cancelar($s, 'Registrado por engano');
        $this->assertSame(FrotaSinistro::CANCELADO, $s->fresh()->situacao);
        $this->assertNull(SinistroService::paradoPorSinistro($v));
    }

    public function test_pendencias_parado_bo_e_orcamento(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $this->servico->registrar($v, $this->dados(['tipo' => 'furto_roubo', 'veiculo_parado' => true, 'ocorrido_em' => now()->subDays(20)->toDateTimeString()]));

        $p = (new PendenciasFrotaService)->doVeiculo($v)->where('categoria', 'sinistro');

        $this->assertSame(3, $p->count());
        $this->assertSame('critica', $p->first(fn ($x) => str_contains($x['mensagem'], 'parado'))['gravidade']);
        $this->assertNotNull($p->first(fn ($x) => str_contains($x['mensagem'], 'B.O.')));
        $this->assertNotNull($p->first(fn ($x) => str_contains($x['mensagem'], 'orçamento')));
        $this->assertFalse((new PendenciasFrotaService)->permiteOs($p->first()));
    }

    public function test_um_cliente_nao_ve_sinistro_do_outro_e_a_tela_registra_e_encerra(): void
    {
        [$a, $adminA] = $this->cliente();
        $motoristaA = $this->motorista;
        [$b, $adminB] = $this->cliente();
        $vA = $this->veiculo($a);
        $this->servico->registrar($vA, $this->dados());

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaSinistro::count());

        $this->actingAs($adminA);
        $this->assertSame(1, FrotaSinistro::count());
        Livewire::test(CreateFrotaSinistro::class)
            ->fillForm(['ativo_id' => $vA->id, 'tipo' => 'avaria', 'ocorrido_em' => now()->subHour()->toDateTimeString(), 'descricao' => 'Pneu estourado', 'motorista_id' => $motoristaA->id])
            ->call('create')->assertHasNoFormErrors();
        $nova = FrotaSinistro::where('descricao', 'Pneu estourado')->sole();
        $this->assertSame($motoristaA->id, $nova->motorista_id);

        Livewire::test(ListFrotaSinistros::class)->callTableAction('encerrar', $nova);
        $this->assertSame(FrotaSinistro::ENCERRADO, $nova->fresh()->situacao);
    }
}
