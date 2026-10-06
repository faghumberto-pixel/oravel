<?php

namespace Tests\Feature;

use App\Filament\Resources\FrotaMultaResource\Pages\ListFrotaMultas;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaMulta;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\MultaService;
use App\Services\Frota\PendenciasFrotaService;
use App\Services\Frota\SaidaVeiculoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 7: multas (condutor sugerido pela saída, pontos, prazos) e CNH nas Pendências. */
class FrotaMultaTest extends TestCase
{
    use DatabaseTransactions;

    private MultaService $servico;

    private FleetDriver $motorista;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new MultaService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_multas', 'tabela_frota_saidas_veiculo']]);
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
        return array_merge(['numero_auto' => 'AUTO-'.uniqid(), 'infracao_em' => now()->subDay()->toDateTimeString(), 'descricao' => 'Excesso de velocidade',
            'gravidade' => 'media', 'valor' => '130.16', 'vencimento' => now()->addDays(30)->toDateString()], $extra);
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

    public function test_registrar_calcula_pontos_pela_gravidade_e_comeca_aberta_sem_condutor(): void
    {
        [$tenant] = $this->cliente();
        $m = $this->servico->registrar($this->veiculo($tenant), $this->dados(['gravidade' => 'gravissima']));

        $this->assertSame(7, $m->pontos);
        $this->assertSame(FrotaMulta::ABERTA, $m->situacao);
        $this->assertNull($m->motorista_id);
    }

    public function test_condutor_e_sugerido_pela_saida_do_veiculo_na_hora_da_infracao(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $saida = (new SaidaVeiculoService)->registrarSaida($v, ['finalidade' => 'cliente', 'motorista_id' => $this->motorista->id, 'destino' => 'X', 'motivo' => 'Y',
            'odometro' => 10000, 'saida_em' => now()->subHours(5)->toDateTimeString()]);

        $dentro = $this->servico->registrar($v, $this->dados(['infracao_em' => now()->subHours(2)->toDateTimeString()]));
        $this->assertSame($this->motorista->id, $dentro->motorista_id);
        $this->assertSame($saida->id, $dentro->saida_veiculo_id);
        $this->assertSame(FrotaMulta::CONDUTOR_INDICADO, $dentro->situacao);

        $antes = $this->servico->registrar($v, $this->dados(['infracao_em' => now()->subHours(9)->toDateTimeString()]));
        $this->assertNull($antes->motorista_id);
    }

    public function test_validacoes_do_registro(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->servico->registrar($v, $this->dados(['numero_auto' => 'A1']));

        $this->erroEm(fn () => $this->servico->registrar($v, $this->dados(['numero_auto' => 'A1'])), 'numero_auto');
        $this->erroEm(fn () => $this->servico->registrar($v, $this->dados(['valor' => 0])), 'valor');
        $this->erroEm(fn () => $this->servico->registrar($v, $this->dados(['gravidade' => 'x'])), 'gravidade');
        $this->erroEm(fn () => $this->servico->registrar($v, $this->dados(['descricao' => ' '])), 'descricao');
        $this->erroEm(fn () => $this->servico->registrar($v, $this->dados(['infracao_em' => now()->addDay()->toDateTimeString()])), 'infracao_em');
    }

    public function test_indicar_pagar_recorrer_e_cancelar_respeitam_a_situacao(): void
    {
        [$tenant] = $this->cliente();
        $m = $this->servico->registrar($this->veiculo($tenant), $this->dados());

        $this->servico->indicarCondutor($m, $this->motorista->id);
        $this->assertSame(FrotaMulta::CONDUTOR_INDICADO, $m->fresh()->situacao);
        $this->servico->recorrer($m->fresh());
        $this->assertSame(FrotaMulta::RECORRIDA, $m->fresh()->situacao);
        $this->erroEm(fn () => $this->servico->pagar($m->fresh(), now()->addDay()->toDateString()), 'pago_em');
        $this->servico->pagar($m->fresh());
        $this->assertSame(FrotaMulta::PAGA, $m->fresh()->situacao);

        $this->erroEm(fn () => $this->servico->pagar($m->fresh()), 'situacao');
        $this->erroEm(fn () => $this->servico->cancelar($m->fresh(), 'x'), 'situacao');
    }

    public function test_cancelar_exige_motivo_e_tira_os_pontos(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $a = $this->servico->registrar($v, $this->dados(['motorista_id' => $this->motorista->id, 'gravidade' => 'grave']));
        $this->assertSame(5, MultaService::pontosDoMotorista($this->motorista));

        $this->erroEm(fn () => $this->servico->cancelar($a, ' '), 'motivo');
        $this->servico->cancelar($a, 'Auto improcedente');
        $this->assertSame(0, MultaService::pontosDoMotorista($this->motorista));
    }

    public function test_pontos_contam_so_12_meses_e_alertam_em_15_e_20(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $this->servico->registrar($v, $this->dados(['motorista_id' => $this->motorista->id, 'gravidade' => 'gravissima', 'infracao_em' => now()->subMonths(14)->toDateTimeString()]));
        $this->assertSame(0, MultaService::pontosDoMotorista($this->motorista));

        foreach (range(1, 3) as $i) {
            $this->servico->registrar($v, $this->dados(['motorista_id' => $this->motorista->id, 'gravidade' => 'grave']));
        }
        $this->assertSame(15, MultaService::pontosDoMotorista($this->motorista));
        $this->assertSame('atencao', (new PendenciasFrotaService)->motoristas()->firstWhere('categoria', 'cnh')['gravidade']);

        $this->servico->registrar($v, $this->dados(['motorista_id' => $this->motorista->id, 'gravidade' => 'grave']));
        $this->assertSame('critica', (new PendenciasFrotaService)->motoristas()->firstWhere('categoria', 'cnh')['gravidade']);
    }

    public function test_cnh_vencida_e_vencendo_entram_nas_pendencias_e_nao_geram_os(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $this->motorista->update(['cnh_expiry_date' => now()->subDays(2)]);
        FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'Ana', 'cnh_expiry_date' => now()->addDays(10), 'active' => true]);
        FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'Inativo', 'cnh_expiry_date' => now()->subYear(), 'active' => false]);

        $lista = (new PendenciasFrotaService)->motoristas();

        $this->assertSame(2, $lista->count());
        $this->assertSame('critica', $lista->first(fn ($p) => str_contains($p['mensagem'], 'João'))['gravidade']);
        $this->assertSame('atencao', $lista->first(fn ($p) => str_contains($p['mensagem'], 'Ana'))['gravidade']);
        $this->assertFalse((new PendenciasFrotaService)->permiteOs($lista->first()));
    }

    public function test_multa_vencida_e_sem_condutor_viram_pendencia_do_veiculo(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $this->servico->registrar($v, $this->dados(['vencimento' => now()->subDays(3)->toDateString(), 'prazo_indicacao' => now()->subDay()->toDateString()]));

        $msg = (new PendenciasFrotaService)->doVeiculo($v)->where('categoria', 'multa');

        $this->assertSame(2, $msg->count());
        $this->assertTrue($msg->every(fn ($p) => $p['gravidade'] === 'critica'));
        $this->assertFalse((new PendenciasFrotaService)->permiteOs($msg->first()));
    }

    public function test_um_cliente_nao_ve_multa_do_outro_e_a_tela_registra_e_paga(): void
    {
        [$a, $adminA] = $this->cliente();
        $motoristaA = $this->motorista;
        [$b, $adminB] = $this->cliente();
        $vA = $this->veiculo($a);
        $this->servico->registrar($vA, $this->dados());

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaMulta::count());

        $this->actingAs($adminA);
        $this->assertSame(1, FrotaMulta::count());
        Livewire::test(ListFrotaMultas::class)
            ->callTableAction('registrar_multa', data: array_merge($this->dados(['numero_auto' => 'TELA-1']), ['ativo_id' => $vA->id, 'motorista_id' => $motoristaA->id]))
            ->assertHasNoTableActionErrors();
        $nova = FrotaMulta::where('numero_auto', 'TELA-1')->sole();
        $this->assertSame($motoristaA->id, $nova->motorista_id);

        Livewire::test(ListFrotaMultas::class)->callTableAction('pagar', $nova, data: ['pago_em' => now()->toDateString()]);
        $this->assertSame(FrotaMulta::PAGA, $nova->fresh()->situacao);
    }
}
