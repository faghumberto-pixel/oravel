<?php

namespace Tests\Feature;

use App\Filament\Pages\FrotaConsumoPorVeiculo;
use App\Filament\Resources\FrotaAbastecimentoResource\Pages\ListFrotaAbastecimentos;
use App\Models\Asset;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaLeituraOdometro;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Frota\AbastecimentoService;
use App\Services\Frota\PendenciasFrotaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fase 9: abastecimento, consumo (tanque cheio), desvio, resumo e baixa do tanque próprio. */
class FrotaAbastecimentoTest extends TestCase
{
    use DatabaseTransactions;

    private AbastecimentoService $servico;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servico = new AbastecimentoService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_abastecimentos', 'tabela_parts', 'tabela_warehouses']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant, int $km = 10000): Asset
    {
        return Asset::create(['tenant_id' => $tenant->id, 'name' => 'Caminhão '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => $km]);
    }

    private function abastecer(Asset $v, int $km, float $litros, array $extra = []): FrotaAbastecimento
    {
        return $this->servico->registrar($v->fresh(), array_merge(['combustivel' => 'diesel_s10', 'litros' => $litros, 'valor_total' => $litros * 6, 'odometro' => $km,
            'abastecido_em' => now()->subDays(40)->addMinutes(intdiv($km, 10) - 1000)->toDateTimeString()], $extra));
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

    public function test_primeiro_tanque_cheio_nao_tem_consumo_e_o_segundo_sim(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);

        $a = $this->abastecer($v, 10000, 50);
        $this->assertNull($a->consumo_km_l);

        $b = $this->abastecer($v, 10400, 80);
        $this->assertSame(400, $b->km_rodado);
        $this->assertSame('5.00', $b->consumo_km_l);
    }

    public function test_abastecimento_parcial_no_meio_entra_na_conta_e_nao_calcula_consumo(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->abastecer($v, 10000, 50);

        $parcial = $this->abastecer($v, 10200, 30, ['tanque_cheio' => false]);
        $this->assertNull($parcial->consumo_km_l);

        $cheio = $this->abastecer($v, 10500, 70);
        $this->assertSame('5.00', $cheio->consumo_km_l);   // 500 km ÷ (30 + 70) L
    }

    public function test_valor_total_ou_por_litro_calcula_o_outro_e_exige_um_deles(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);

        $a = $this->servico->registrar($v, ['combustivel' => 'gasolina', 'litros' => 40, 'valor_litro' => '6,25', 'odometro' => 10000]);
        $this->assertSame('250.00', $a->valor_total);
        $b = $this->servico->registrar($v->fresh(), ['combustivel' => 'gasolina', 'litros' => 40, 'valor_total' => '240', 'odometro' => 10100]);
        $this->assertSame('6.000', $b->valor_litro);
        $this->erroEm(fn () => $this->servico->registrar($v->fresh(), ['combustivel' => 'gasolina', 'litros' => 40, 'odometro' => 10200]), 'valor_total');
    }

    public function test_validacoes_e_ordem_cronologica_e_km_menor(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $base = ['combustivel' => 'diesel_s10', 'litros' => 50, 'valor_total' => 300, 'odometro' => 10000];

        $this->erroEm(fn () => $this->servico->registrar($v, array_merge($base, ['combustivel' => 'x'])), 'combustivel');
        $this->erroEm(fn () => $this->servico->registrar($v, array_merge($base, ['litros' => 0])), 'litros');
        $this->erroEm(fn () => $this->servico->registrar($v, array_merge($base, ['abastecido_em' => now()->addDay()->toDateTimeString()])), 'abastecido_em');
        $this->erroEm(fn () => $this->servico->registrar($v, array_merge($base, ['origem' => 'x'])), 'origem');
        $this->erroEm(fn () => $this->servico->registrar($this->veiculo($tenant)->forceFill(['grupo' => Asset::GRUPO_MAQUINA]), $base), 'ativo');

        $this->servico->registrar($v, $base);
        $this->erroEm(fn () => $this->servico->registrar($v->fresh(), array_merge($base, ['odometro' => 10100, 'abastecido_em' => now()->subDay()->toDateTimeString()])), 'abastecido_em');
        $this->erroEm(fn () => $this->servico->registrar($v->fresh(), array_merge($base, ['odometro' => 500])), 'odometro');
        $this->assertSame(1, FrotaAbastecimento::where('ativo_id', $v->id)->count());
        $this->assertSame('abastecimento', FrotaLeituraOdometro::where('ativo_id', $v->id)->latest('lido_em')->value('origem'));
    }

    public function test_desvio_compara_com_a_media_das_ultimas_e_precisa_de_base(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->abastecer($v, 10000, 50);
        $km = 10000;
        foreach ([500, 500, 500, 500] as $rodado) {      // 4 consumos de 5,0 km/l
            $km += $rodado;
            $ultimo = $this->abastecer($v, $km, 100);
        }
        $this->assertSame('normal', AbastecimentoService::desvio($ultimo)['situacao']);

        $km += 500;
        $ruim = $this->abastecer($v, $km, 140);     // 500 ÷ 140 = 3,57 km/l = 71% da média
        $this->assertSame('atencao', AbastecimentoService::desvio($ruim)['situacao']);

        $km += 500;
        $pessimo = $this->abastecer($v, $km, 200);  // 2,5 km/l
        $this->assertSame('critica', AbastecimentoService::desvio($pessimo)['situacao']);
        $this->assertSame('sem_base', AbastecimentoService::desvio(FrotaAbastecimento::where('ativo_id', $v->id)->orderBy('abastecido_em')->skip(1)->first())['situacao']);
    }

    public function test_resumo_do_periodo_calcula_km_l_e_custo_por_km(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->abastecer($v, 10000, 50);
        $this->abastecer($v, 10500, 100, ['valor_total' => 600]);

        $r = AbastecimentoService::resumo($v, 3);

        $this->assertSame(2, $r['abastecimentos']);
        $this->assertSame(150.0, $r['litros']);
        $this->assertSame(500, $r['km']);
        $this->assertSame(5.0, $r['km_l']);
        $this->assertSame(1.2, $r['custo_km']);
    }

    public function test_tanque_proprio_baixa_o_estoque_e_saldo_insuficiente_recusa_tudo(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $peca = Part::create(['tenant_id' => $tenant->id, 'part_category_id' => PartCategory::create(['tenant_id' => $tenant->id, 'name' => 'Comb'])->id,
            'sku' => 'D'.uniqid(), 'name' => 'Diesel S10', 'unit_of_measure' => 'LT', 'minimum_stock' => 100]);
        $alm = Warehouse::create(['tenant_id' => $tenant->id, 'name' => 'Tanque', 'is_active' => true]);
        WarehouseStock::create(['warehouse_id' => $alm->id, 'part_id' => $peca->id, 'current_quantity' => 120.5, 'reserved_quantity' => 0]);
        $vinculo = ['origem' => 'tanque_proprio', 'peca_id' => $peca->id, 'almoxarifado_id' => $alm->id];

        $this->abastecer($v, 10000, 50.25, $vinculo);
        $this->assertSame(70.25, (float) WarehouseStock::where('part_id', $peca->id)->value('current_quantity'));

        $this->erroEm(fn () => $this->abastecer($v, 10100, 100, $vinculo), 'litros');
        $this->assertSame(70.25, (float) WarehouseStock::where('part_id', $peca->id)->value('current_quantity'));
        $this->assertSame(1, FrotaAbastecimento::where('ativo_id', $v->id)->count());

        $externo = $this->abastecer($v, 10100, 40, ['peca_id' => $peca->id, 'almoxarifado_id' => $alm->id]);   // posto: ignora o vínculo
        $this->assertNull($externo->peca_id);
        $this->assertSame(70.25, (float) WarehouseStock::where('part_id', $peca->id)->value('current_quantity'));
    }

    public function test_consumo_ruim_recente_vira_pendencia_do_veiculo(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $km = 10000;
        $this->servico->registrar($v, ['combustivel' => 'diesel_s10', 'litros' => 50, 'valor_total' => 300, 'odometro' => $km, 'abastecido_em' => now()->subDays(12)->toDateTimeString()]);
        foreach ([11, 10, 9] as $dias) {
            $km += 500;
            $this->servico->registrar($v->fresh(), ['combustivel' => 'diesel_s10', 'litros' => 100, 'valor_total' => 600, 'odometro' => $km, 'abastecido_em' => now()->subDays($dias)->toDateTimeString()]);
        }
        $this->assertSame(0, (new PendenciasFrotaService)->doVeiculo($v->fresh())->where('categoria', 'consumo')->count());

        $km += 500;
        $this->servico->registrar($v->fresh(), ['combustivel' => 'diesel_s10', 'litros' => 200, 'valor_total' => 1200, 'odometro' => $km, 'abastecido_em' => now()->subDays(2)->toDateTimeString()]);

        $p = (new PendenciasFrotaService)->doVeiculo($v->fresh())->firstWhere('categoria', 'consumo');
        $this->assertSame('critica', $p['gravidade']);
        $this->assertStringContainsString('abaixo do habitual', $p['mensagem']);
    }

    public function test_um_cliente_nao_ve_abastecimento_do_outro_e_as_telas_funcionam(): void
    {
        [$a, $adminA] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $vA = $this->veiculo($a);
        $this->servico->registrar($vA, ['combustivel' => 'diesel_s10', 'litros' => 50, 'valor_total' => 300, 'odometro' => 10000, 'abastecido_em' => now()->subDay()->toDateTimeString()]);

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaAbastecimento::count());

        $this->actingAs($adminA);
        $this->assertSame(1, FrotaAbastecimento::count());
        Livewire::test(ListFrotaAbastecimentos::class)
            ->callTableAction('registrar_abastecimento', data: ['ativo_id' => $vA->id, 'abastecido_em' => now()->toDateTimeString(), 'odometro' => 10500, 'combustivel' => 'diesel_s10',
                'litros' => '100', 'valor_total' => '600', 'tanque_cheio' => true, 'origem' => 'externo'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('5.00', FrotaAbastecimento::where('ativo_id', $vA->id)->latest('abastecido_em')->first()->consumo_km_l);

        Livewire::test(FrotaConsumoPorVeiculo::class)->assertSee($vA->placa)->assertSee('5,00');
    }
}
