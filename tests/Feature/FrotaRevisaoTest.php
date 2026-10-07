<?php

namespace Tests\Feature;

use App\Filament\Resources\FrotaPlanoRevisaoResource\Pages\ListFrotaPlanosRevisao;
use App\Models\Asset;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaPlanoRevisao;
use App\Models\FrotaRevisaoRealizada;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\CustoFrotaService;
use App\Services\Frota\PendenciasFrotaService;
use App\Services\Frota\RevisaoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 11: revisões preventivas por km e tempo (planos, realização, situação, pendência e custo). */
class FrotaRevisaoTest extends TestCase
{
    use DatabaseTransactions;

    private RevisaoService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new RevisaoService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_planos_revisao']]);
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

    private function erroEm(callable $fn, string $campo): void
    {
        try {
            $fn();
            $this->fail("Deveria recusar ({$campo}).");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($campo, $e->errors());
        }
    }

    public function test_criar_plano_valida_nome_intervalo_e_duplicidade(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);

        $this->erroEm(fn () => $this->servico->criarPlano($v, ['nome' => ' ', 'intervalo_km' => 1000]), 'nome');
        $this->erroEm(fn () => $this->servico->criarPlano($v, ['nome' => 'Freios']), 'intervalo_km');
        $this->erroEm(fn () => $this->servico->criarPlano($this->veiculo($tenant)->forceFill(['grupo' => Asset::GRUPO_MAQUINA]), ['nome' => 'X', 'intervalo_km' => 1]), 'ativo');

        $p = $this->servico->criarPlano($v, ['nome' => 'Freios', 'intervalo_km' => 30000]);
        $this->erroEm(fn () => $this->servico->criarPlano($v, ['nome' => 'freios', 'intervalo_dias' => 90]), 'nome');
        $this->servico->desativar($p);
        $this->assertSame('freios', $this->servico->criarPlano($v, ['nome' => 'freios', 'intervalo_dias' => 90])->nome);
    }

    public function test_aplicar_padrao_cria_so_o_que_falta(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->servico->criarPlano($v, ['nome' => 'Correia', 'intervalo_km' => 50000]);

        $this->assertSame(count(RevisaoService::PADRAO) - 1, $this->servico->aplicarPadrao($v));
        $this->assertSame(0, $this->servico->aplicarPadrao($v));
        $this->assertSame(50000, FrotaPlanoRevisao::where('ativo_id', $v->id)->where('nome', 'Correia')->value('intervalo_km'));
    }

    public function test_situacao_sem_registro_em_dia_proxima_e_vencida_pelo_que_vencer_primeiro(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $p = $this->servico->criarPlano($v, ['nome' => 'Freios', 'intervalo_km' => 10000, 'intervalo_dias' => 365]);
        $this->assertSame(RevisaoService::SEM_REGISTRO, RevisaoService::situacao($p)['situacao']);

        $this->servico->registrar($p, ['odometro' => 10000]);
        $this->assertSame(RevisaoService::EM_DIA, RevisaoService::situacao($p->fresh())['situacao']);
        $this->assertSame(20000, RevisaoService::situacao($p->fresh())['proxima_km']);

        $v->update(['odometro_atual' => 19500]);
        $this->assertSame(RevisaoService::PROXIMA, RevisaoService::situacao($p->fresh())['situacao']);

        $v->update(['odometro_atual' => 20100]);
        $this->assertSame(RevisaoService::VENCIDA, RevisaoService::situacao($p->fresh())['situacao']);
    }

    public function test_vence_por_dias_mesmo_com_km_sobrando(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $p = $this->servico->criarPlano($v, ['nome' => 'Fluido', 'intervalo_km' => 100000, 'intervalo_dias' => 60]);
        $this->servico->registrar($p, ['odometro' => 10000, 'realizada_em' => now()->subDays(70)->toDateString()]);

        $s = RevisaoService::situacao($p->fresh());

        $this->assertSame(RevisaoService::VENCIDA, $s['situacao']);
        $this->assertStringContainsString('venceu há 10 dia(s)', $s['mensagem']);
    }

    public function test_registrar_zera_o_contador_gera_leitura_e_recusa_km_menor_e_data_futura(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $p = $this->servico->criarPlano($v, ['nome' => 'Filtro de ar', 'intervalo_km' => 5000]);
        $this->servico->registrar($p, ['odometro' => 10000]);
        $v->update(['odometro_atual' => 15500]);
        $this->assertSame(RevisaoService::VENCIDA, RevisaoService::situacao($p->fresh())['situacao']);

        $this->servico->registrar($p->fresh(), ['odometro' => 15500, 'custo' => '120,50']);
        $this->assertSame(RevisaoService::EM_DIA, RevisaoService::situacao($p->fresh())['situacao']);
        $this->assertSame('revisao', FrotaLeituraOdometro::where('ativo_id', $v->id)->latest('lido_em')->value('origem'));

        $this->erroEm(fn () => $this->servico->registrar($p->fresh(), ['odometro' => 100]), 'odometro');
        $this->erroEm(fn () => $this->servico->registrar($p->fresh(), ['odometro' => 16000, 'realizada_em' => now()->addDay()->toDateString()]), 'realizada_em');
        $this->erroEm(fn () => $this->servico->registrar($p->fresh(), ['odometro' => 16000, 'custo' => -1]), 'custo');
        $this->assertSame(2, FrotaRevisaoRealizada::where('plano_id', $p->id)->count());

        $this->servico->desativar($p);
        $this->erroEm(fn () => $this->servico->registrar($p->fresh(), ['odometro' => 16000]), 'plano');
    }

    public function test_pendencias_revisao_vencida_e_proxima_e_gerar_os_preventiva(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $freios = $this->servico->criarPlano($v, ['nome' => 'Freios', 'intervalo_km' => 5000]);
        $correia = $this->servico->criarPlano($v, ['nome' => 'Correia', 'intervalo_km' => 6000]);
        $filtro = $this->servico->criarPlano($v, ['nome' => 'Filtro', 'intervalo_km' => 50000]);
        foreach ([$freios, $correia, $filtro] as $plano) {
            $this->servico->registrar($plano, ['odometro' => 10000]);
        }
        $v->update(['odometro_atual' => 15200]);   // freios venceu em 15.000; correia vence em 16.000 (faltam 800); filtro em dia

        $p = (new PendenciasFrotaService)->doVeiculo($v->fresh())->where('categoria', 'revisao');

        $this->assertSame(2, $p->count());
        $this->assertSame('critica', $p->first(fn ($x) => str_contains($x['mensagem'], 'Freios'))['gravidade']);
        $this->assertSame('atencao', $p->first(fn ($x) => str_contains($x['mensagem'], 'Correia'))['gravidade']);

        $os = (new PendenciasFrotaService)->gerarOs($p->first(fn ($x) => str_contains($x['mensagem'], 'Freios')));
        $this->assertSame(MaintenanceOrder::TYPE_PREVENTIVE, $os->maintenance_type);
    }

    public function test_revisao_sem_os_entra_no_custo_de_manutencao_e_com_os_nao_duplica(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $p = $this->servico->criarPlano($v, ['nome' => 'Freios', 'intervalo_km' => 5000]);
        $this->servico->registrar($p, ['odometro' => 10000, 'custo' => 400]);
        $os = MaintenanceOrder::create(['tenant_id' => $tenant->id, 'asset_id' => $v->id, 'maintenance_type' => 'Preventiva', 'status' => 'Aberto', 'internal_status' => 'aguardando_diagnostico', 'total_order_cost' => 900]);
        $this->servico->registrar($p->fresh(), ['odometro' => 10000, 'custo' => 900, 'ordem_servico_id' => $os->id]);

        $this->assertSame(1300.0, (new CustoFrotaService)->veiculo($v->fresh(), 3)['componentes']['manutencao']);   // 400 (sem OS) + 900 (da OS); os 900 da revisão com OS não repetem
    }

    public function test_um_cliente_nao_ve_revisao_do_outro_e_a_tela_funciona(): void
    {
        [$a, $adminA] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $vA = $this->veiculo($a);
        $this->servico->criarPlano($vA, ['nome' => 'Freios', 'intervalo_km' => 5000]);

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaPlanoRevisao::count());

        $this->actingAs($adminA);
        $this->assertSame(1, FrotaPlanoRevisao::count());
        Livewire::test(ListFrotaPlanosRevisao::class)
            ->callTableAction('novo_item', data: ['ativo_id' => $vA->id, 'nome' => 'Correia', 'intervalo_km' => 60000])
            ->assertHasNoTableActionErrors();
        $plano = FrotaPlanoRevisao::where('nome', 'Correia')->sole();

        Livewire::test(ListFrotaPlanosRevisao::class)->callTableAction('registrar', $plano, data: ['realizada_em' => now()->toDateString(), 'odometro' => 10000]);
        $this->assertSame(1, FrotaRevisaoRealizada::where('plano_id', $plano->id)->count());
        Livewire::test(ListFrotaPlanosRevisao::class)->callTableAction('aplicar_padrao', data: ['ativo_id' => $vA->id]);
        $this->assertSame(count(RevisaoService::PADRAO) - 1 + 2, FrotaPlanoRevisao::where('ativo_id', $vA->id)->where('ativo', true)->count());
    }
}
