<?php

namespace Tests\Feature;

use App\Filament\Pages\FrotaDisponibilidade;
use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\FrotaSaidaVeiculo;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\DisponibilidadeFrotaService;
use App\Services\Frota\SinistroService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 13: disponibilidade (paradas + sinistros, união dos períodos, recorte no período). */
class FrotaDisponibilidadeTest extends TestCase
{
    use DatabaseTransactions;

    private DisponibilidadeFrotaService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new DisponibilidadeFrotaService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_asset_downtime_events']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    /** Veículo cadastrado há bastante tempo, para o período inteiro contar. */
    private function veiculo(Tenant $tenant): Asset
    {
        $v = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Carro '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000]);
        $v->forceFill(['created_at' => now()->subYears(2)])->saveQuietly();

        return $v;
    }

    private function parada(Asset $v, $de, $ate, string $motivo = 'quebra'): AssetDowntimeEvent
    {
        return AssetDowntimeEvent::create(['tenant_id' => $v->tenant_id, 'asset_id' => $v->id, 'started_at' => $de, 'ended_at' => $ate, 'reason' => $motivo]);
    }

    public function test_sem_paradas_e_100_por_cento(): void
    {
        [$tenant] = $this->cliente();
        $r = $this->servico->veiculo($this->veiculo($tenant), 3);

        $this->assertSame(100.0, $r['disponibilidade']);
        $this->assertSame(0, $r['paradas']);
        $this->assertSame('boa', $r['situacao']);
        $this->assertNull($r['causa_principal']);
    }

    public function test_parada_reduz_a_disponibilidade_proporcional_ao_periodo(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $periodoH = $this->servico->veiculo($v, 3)['periodo_horas'];
        $this->parada($v, now()->subHours(10), now()->subHours(5));   // 5 h dentro do período

        $r = $this->servico->veiculo($v, 3);

        $this->assertSame(5.0, $r['parado_horas']);
        $this->assertEqualsWithDelta(100 * (1 - 5 / $periodoH), $r['disponibilidade'], 0.1);
        $this->assertSame(1, $r['paradas']);
        $this->assertSame('Quebra', $r['causa_principal']);
    }

    public function test_paradas_sobrepostas_e_sinistro_junto_nao_contam_duas_vezes(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $this->parada($v, now()->subHours(30), now()->subHours(20));
        $this->parada($v, now()->subHours(25), now()->subHours(10), 'aguardando_peca');   // sobrepõe: união = 20 h (de -30 a -10)
        $s = (new SinistroService)->registrar($v, ['tipo' => 'colisao', 'ocorrido_em' => now()->subHours(15)->toDateTimeString(), 'descricao' => 'x', 'veiculo_parado' => true]);
        (new SinistroService)->voltouARodar($s, now()->subHours(5)->toDateTimeString());   // de -15 a -5: estende a união até -5 → 25 h

        $r = $this->servico->veiculo($v, 3);

        $this->assertSame(25.0, $r['parado_horas']);
        $this->assertSame(3, $r['paradas']);
    }

    public function test_ocioso_sem_uso_e_sinistro_cancelado_nao_contam(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $this->parada($v, now()->subDays(5), now()->subDays(1), 'ocioso_sem_uso');
        $s = (new SinistroService)->registrar($v, ['tipo' => 'avaria', 'ocorrido_em' => now()->subDays(3)->toDateTimeString(), 'descricao' => 'x', 'veiculo_parado' => true]);
        (new SinistroService)->cancelar($s, 'engano');

        $this->assertSame(100.0, $this->servico->veiculo($v, 3)['disponibilidade']);
    }

    public function test_parada_antiga_e_recortada_ao_periodo_e_parada_em_aberto_vai_ate_agora(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->parada($v, now()->subMonths(8), now()->subMonths(7));        // fora do período
        $this->assertSame(0.0, $this->servico->veiculo($v, 3)['parado_horas']);

        $this->parada($v, now()->subHours(48), null);                        // ainda parado
        $r = $this->servico->veiculo($v, 3);
        $this->assertEqualsWithDelta(48.0, $r['parado_horas'], 0.1);
    }

    public function test_veiculo_novo_so_conta_a_partir_do_cadastro_e_horas_fora_nao_reduzem(): void
    {
        [$tenant] = $this->cliente();
        $novo = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Novo', 'tag' => 'N-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL, 'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'NOV1A11']);
        $novo->forceFill(['created_at' => now()->subHours(100)])->saveQuietly();
        FrotaSaidaVeiculo::create(['tenant_id' => $tenant->id, 'ativo_id' => $novo->id, 'finalidade' => 'cliente', 'destino' => 'X', 'motivo' => 'Y', 'saida_em' => now()->subHours(40),
            'odometro_saida' => 1, 'retorno_em' => now()->subHours(30), 'odometro_retorno' => 2]);

        $r = $this->servico->veiculo($novo, 3);

        $this->assertEqualsWithDelta(100.0, $r['periodo_horas'], 0.1);
        $this->assertSame(10.0, $r['fora_horas']);
        $this->assertSame(100.0, $r['disponibilidade']);
    }

    public function test_situacao_por_faixa_e_um_cliente_nao_ve_o_do_outro_e_a_tela_funciona(): void
    {
        [$a, $adminA] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $vA = $this->veiculo($a);
        $horas = (int) ($this->servico->veiculo($vA, 3)['periodo_horas'] * 0.07);   // ~7% do período parado = 93%: atenção
        $this->parada($vA, now()->subHours($horas + 1), now()->subHours(1));
        $this->assertSame('atencao', $this->servico->veiculo($vA, 3)['situacao']);

        $this->actingAs($adminB);
        $this->assertSame(0, AssetDowntimeEvent::count());
        Livewire::test(FrotaDisponibilidade::class)->assertSee('Nenhum veículo cadastrado');

        $this->actingAs($adminA);
        Livewire::test(FrotaDisponibilidade::class)->assertSee($vA->placa)->assertSee('Disponibilidade média da frota');
    }
}
