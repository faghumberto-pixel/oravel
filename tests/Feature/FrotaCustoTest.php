<?php

namespace Tests\Feature;

use App\Filament\Pages\FrotaCustoPorVeiculo;
use App\Filament\Resources\FrotaCustoAvulsoResource\Pages\ManageFrotaCustosAvulsos;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\FrotaBateria;
use App\Models\FrotaCustoAvulso;
use App\Models\FrotaPneu;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\AbastecimentoService;
use App\Services\Frota\BateriaService;
use App\Services\Frota\CustoFrotaService;
use App\Services\Frota\MultaService;
use App\Services\Frota\OleoService;
use App\Services\Frota\PneuService;
use App\Services\Frota\SinistroService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 10: custo total por veículo (soma de todas as fontes, rateio e custo por km). */
class FrotaCustoTest extends TestCase
{
    use DatabaseTransactions;

    private CustoFrotaService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new CustoFrotaService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_custos_avulsos']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant): Asset
    {
        return Asset::create(['tenant_id' => $tenant->id, 'name' => 'Caminhão '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000]);
    }

    public function test_soma_todas_as_fontes_e_calcula_custo_por_km(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $motorista = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'João', 'cnh_expiry_date' => now()->addYear(), 'active' => true]);

        (new AbastecimentoService)->registrar($v, ['combustivel' => 'diesel_s10', 'litros' => 100, 'valor_total' => 600, 'odometro' => 10000, 'abastecido_em' => now()->subDays(3)->toDateTimeString()]);
        MaintenanceOrder::create(['tenant_id' => $tenant->id, 'asset_id' => $v->id, 'maintenance_type' => 'Corretiva', 'status' => 'Aberto', 'internal_status' => 'aguardando_diagnostico', 'total_order_cost' => 1000]);
        $pneu = FrotaPneu::create(['tenant_id' => $tenant->id, 'numero_fogo' => 'F1', 'custo' => 2000]);
        (new PneuService)->montar($pneu, $v->fresh(), 'E1-LE', 10100, $admin);
        $bat = FrotaBateria::create(['tenant_id' => $tenant->id, 'marca' => 'M', 'custo' => 800]);
        (new BateriaService)->instalar($bat, $v->fresh(), 10200, $admin);
        (new OleoService)->registrar($v->fresh(), ['tipo' => 'troca', 'litros' => 20, 'produto' => '15W40', 'odometro' => 10300, 'custo' => 300]);
        (new MultaService)->registrar($v, ['numero_auto' => 'A1', 'infracao_em' => now()->subDay()->toDateTimeString(), 'descricao' => 'x', 'gravidade' => 'leve', 'valor' => 130, 'vencimento' => now()->addMonth()->toDateString()]);
        (new MultaService)->registrar($v, ['numero_auto' => 'A2', 'infracao_em' => now()->subDay()->toDateTimeString(), 'descricao' => 'x', 'gravidade' => 'leve', 'valor' => 999, 'vencimento' => now()->addMonth()->toDateString(), 'quem_paga' => 'motorista']);
        (new SinistroService)->registrar($v, ['tipo' => 'avaria', 'ocorrido_em' => now()->subDay()->toDateTimeString(), 'descricao' => 'x', 'valor_franquia' => 500, 'valor_orcamento' => 9000]);
        FrotaCustoAvulso::create(['tenant_id' => $tenant->id, 'ativo_id' => $v->id, 'tipo' => 'pedagio', 'data' => now()->toDateString(), 'valor' => 70]);

        $r = $this->servico->veiculo($v->fresh(), 3);

        $this->assertSame(['combustivel' => 600.0, 'manutencao' => 1000.0, 'pneus' => 2000.0, 'baterias' => 800.0, 'oleo' => 300.0, 'multas' => 130.0, 'sinistros' => 500.0, 'avulsos' => 70.0], $r['componentes']);
        $this->assertSame(5400.0, $r['total']);
        $this->assertSame(300, $r['km']);
        $this->assertSame(18.0, $r['custo_km']);
    }

    public function test_multa_cancelada_e_sinistro_cancelado_nao_contam_e_orcamento_so_sem_os_nem_franquia(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $multas = new MultaService;
        $m = $multas->registrar($v, ['numero_auto' => 'A1', 'infracao_em' => now()->subDay()->toDateTimeString(), 'descricao' => 'x', 'gravidade' => 'leve', 'valor' => 130, 'vencimento' => now()->addMonth()->toDateString()]);
        $multas->cancelar($m, 'improcedente');
        $sinistros = new SinistroService;
        $dados = ['tipo' => 'avaria', 'ocorrido_em' => now()->subDay()->toDateTimeString(), 'descricao' => 'x'];
        $cancelado = $sinistros->registrar($v, $dados + ['valor_orcamento' => 7000]);
        $sinistros->cancelar($cancelado, 'engano');
        $semOs = $sinistros->registrar($v, $dados + ['valor_orcamento' => 3000]);
        $comOs = $sinistros->registrar($v, $dados + ['valor_orcamento' => 5000]);
        $sinistros->gerarOs($comOs);

        $r = $this->servico->veiculo($v, 3)['componentes'];

        $this->assertSame(0.0, $r['multas']);
        $this->assertSame(3000.0, $r['sinistros']);   // só o sem OS; o com OS entra pela manutenção
    }

    public function test_rateio_anual_divide_por_mes_e_so_conta_os_meses_do_periodo(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        FrotaCustoAvulso::create(['tenant_id' => $tenant->id, 'ativo_id' => $v->id, 'tipo' => 'seguro', 'data' => now()->subMonths(2)->startOfMonth()->toDateString(), 'valor' => 1200, 'rateio_meses' => 12]);

        $this->assertSame(300.0, $this->servico->veiculo($v, 3)['componentes']['avulsos']);   // 3 meses de 100
        $this->assertSame(100.0, $this->servico->veiculo($v, 1)['componentes']['avulsos']);   // só o mês atual
        $this->assertSame(300.0, $this->servico->veiculo($v, 12)['componentes']['avulsos']);  // parcelas futuras ainda não entram... só 3 já correram
    }

    public function test_custo_fora_do_periodo_nao_entra_e_sem_leituras_nao_tem_custo_por_km(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        (new AbastecimentoService)->registrar($v, ['combustivel' => 'diesel_s10', 'litros' => 100, 'valor_total' => 600, 'odometro' => 10000, 'abastecido_em' => now()->subMonths(8)->toDateTimeString()]);

        $this->assertSame(0.0, $this->servico->veiculo($v->fresh(), 3)['total']);
        $this->assertSame(600.0, $this->servico->veiculo($v->fresh(), 12)['total']);
        $this->assertNull($this->servico->veiculo($v->fresh(), 3)['custo_km']);
    }

    public function test_um_cliente_nao_ve_custo_do_outro_e_as_telas_funcionam(): void
    {
        [$a, $adminA] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $vA = $this->veiculo($a);
        FrotaCustoAvulso::create(['tenant_id' => $a->id, 'ativo_id' => $vA->id, 'tipo' => 'ipva', 'data' => now()->toDateString(), 'valor' => 400]);

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaCustoAvulso::count());
        Livewire::test(FrotaCustoPorVeiculo::class)->assertSee('Nenhum custo no período');

        $this->actingAs($adminA);
        Livewire::test(FrotaCustoPorVeiculo::class)->assertSee($vA->placa)->assertSee('400,00');
        Livewire::test(ManageFrotaCustosAvulsos::class)
            ->callAction('create', data: ['ativo_id' => $vA->id, 'tipo' => 'seguro', 'data' => now()->toDateString(), 'valor' => 1200, 'rateio_meses' => 12])
            ->assertHasNoActionErrors();
        $this->assertSame(1200.0, (float) FrotaCustoAvulso::where('tipo', 'seguro')->value('valor'));
        $this->assertSame($adminA->id, FrotaCustoAvulso::where('tipo', 'seguro')->value('registrado_por'));
    }
}
