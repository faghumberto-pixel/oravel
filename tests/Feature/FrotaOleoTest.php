<?php

namespace Tests\Feature;

use App\Livewire\OleoFrotaMobile;
use App\Models\Asset;
use App\Models\FrotaBateria;
use App\Models\FrotaColetaOleoUsado;
use App\Models\FrotaLeituraOdometro;
use App\Models\FrotaPneu;
use App\Models\FrotaTrocaOleo;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Frota\BateriaService;
use App\Services\Frota\CatalogoEstoqueFrota;
use App\Services\Frota\EstoqueFrotaService;
use App\Services\Frota\OleoService;
use App\Services\Frota\OleoStatus;
use App\Services\Frota\PneuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Gestão de Frota, Fases 4 e 4B: óleo (troca, reposição, coleta) e baixa/entrada no Almoxarifado. */
class FrotaOleoTest extends TestCase
{
    use DatabaseTransactions;

    private OleoService $oleo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oleo = new OleoService;
    }

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_trocas_oleo', 'tabela_frota_planos_oleo', 'tabela_frota_coletas_oleo_usado', 'tabela_parts', 'tabela_warehouses']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant, array $extra = []): Asset
    {
        return Asset::create(array_merge(['tenant_id' => $tenant->id, 'name' => 'Caminhão '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000], $extra));
    }

    private function dados(array $extra = []): array
    {
        return array_merge(['tipo' => 'troca', 'litros' => 20, 'produto' => '15W40', 'odometro' => 10000], $extra);
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

    /** @return array{0: Part, 1: Warehouse} peça e almoxarifado do cliente atual (logado). */
    private function estoque(Tenant $tenant, string $nome = 'Óleo 15W40', string $unidade = 'LT', int $saldo = 0): array
    {
        $categoria = PartCategory::create(['tenant_id' => $tenant->id, 'name' => 'Cat '.uniqid()]);
        $peca = Part::create(['tenant_id' => $tenant->id, 'part_category_id' => $categoria->id, 'sku' => 'S'.uniqid(), 'name' => $nome, 'unit_of_measure' => $unidade, 'minimum_stock' => 1]);
        $alm = Warehouse::create(['tenant_id' => $tenant->id, 'name' => 'Almox '.uniqid(), 'is_active' => true]);
        if ($saldo > 0) {
            WarehouseStock::create(['warehouse_id' => $alm->id, 'part_id' => $peca->id, 'current_quantity' => $saldo, 'reserved_quantity' => 0]);
        }

        return [$peca, $alm];
    }

    private function saldo(Part $peca, Warehouse $alm): float
    {
        return (float) (WarehouseStock::where('part_id', $peca->id)->where('warehouse_id', $alm->id)->value('current_quantity') ?? 0);
    }

    public function test_troca_calcula_proxima_por_km_e_por_dias_e_gera_leitura_de_odometro(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->oleo->salvarPlano($v, ['intervalo_km' => 10000, 'intervalo_dias' => 180]);

        $r = $this->oleo->registrar($v, $this->dados(['odometro' => 12000]));

        $this->assertSame(22000, $r->proxima_troca_odometro);
        $this->assertSame(now()->addDays(180)->toDateString(), $r->proxima_troca_data->toDateString());
        $this->assertSame('oleo', FrotaLeituraOdometro::where('ativo_id', $v->id)->latest('lido_em')->value('origem'));
        $this->assertSame(12000, (int) floor((float) $v->fresh()->odometro_atual));
    }

    public function test_reposicao_nao_zera_a_proxima_troca_e_litros_fracionados_sao_recusados(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->oleo->salvarPlano($v, ['intervalo_km' => 10000]);
        $this->oleo->registrar($v, $this->dados(['odometro' => 10000]));

        $rep = $this->oleo->registrar($v, $this->dados(['tipo' => 'reposicao', 'litros' => 2, 'odometro' => 14000]));

        $this->assertNull($rep->proxima_troca_odometro);
        $this->assertSame(20000, OleoStatus::para($v->fresh())['proxima_km']);
        $this->erroEm(fn () => $this->oleo->registrar($v, $this->dados(['litros' => '2.5', 'odometro' => 14500])), 'litros');
    }

    public function test_situacao_proxima_e_vencida_pelo_que_vencer_primeiro(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->oleo->salvarPlano($v, ['intervalo_km' => 10000, 'intervalo_dias' => 365]);
        $this->assertSame(OleoStatus::SEM_REGISTRO, OleoStatus::para($v)['situacao']);

        $this->oleo->registrar($v, $this->dados(['odometro' => 10000]));
        $this->assertSame(OleoStatus::EM_DIA, OleoStatus::para($v->fresh())['situacao']);

        $v->update(['odometro_atual' => 19700]);
        $this->assertSame(OleoStatus::PROXIMA, OleoStatus::para($v->fresh())['situacao']);

        $v->update(['odometro_atual' => 20100]);
        $this->assertSame(OleoStatus::VENCIDA, OleoStatus::para($v->fresh())['situacao']);
    }

    public function test_consumo_anormal_so_aparece_quando_a_reposicao_passa_do_limite(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->oleo->registrar($v, $this->dados(['odometro' => 10000]));
        $this->oleo->registrar($v, $this->dados(['tipo' => 'reposicao', 'litros' => 1, 'odometro' => 12000]));
        $this->assertNull(OleoStatus::consumoAnormal($v->fresh()));

        $this->oleo->registrar($v, $this->dados(['tipo' => 'reposicao', 'litros' => 6, 'odometro' => 12500]));
        $this->assertSame(7.0, OleoStatus::consumoAnormal($v->fresh())['litros']);
    }

    public function test_odometro_menor_recusa_e_nao_grava_nada(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);

        $this->erroEm(fn () => $this->oleo->registrar($v, $this->dados(['odometro' => 500])), 'odometro');
        $this->assertSame(0, FrotaTrocaOleo::where('ativo_id', $v->id)->count());
    }

    public function test_coleta_liga_so_trocas_sem_destinacao(): void
    {
        [$tenant] = $this->cliente();
        $v = $this->veiculo($tenant);
        $t1 = $this->oleo->registrar($v, $this->dados(['odometro' => 10000, 'litros' => 20]));
        $t2 = $this->oleo->registrar($v, $this->dados(['odometro' => 11000, 'litros' => 18]));
        $rep = $this->oleo->registrar($v, $this->dados(['tipo' => 'reposicao', 'litros' => 1, 'odometro' => 11500]));

        $this->assertSame(2, FrotaTrocaOleo::pendenteDeDestinacao()->where('ativo_id', $v->id)->count());
        $this->erroEm(fn () => $this->oleo->registrarColeta(['coletada_em' => now()->toDateString(), 'empresa_coletora' => 'Coleta SA'], [$rep->id], $tenant->id), 'trocas');

        $coleta = $this->oleo->registrarColeta(['coletada_em' => now()->toDateString(), 'empresa_coletora' => 'Coleta SA'], [$t1->id, $t2->id], $tenant->id);

        $this->assertSame(38, $coleta->litros);
        $this->assertSame(0, FrotaTrocaOleo::pendenteDeDestinacao()->where('ativo_id', $v->id)->count());
        $this->erroEm(fn () => $this->oleo->registrarColeta(['coletada_em' => now()->toDateString(), 'empresa_coletora' => 'X'], [$t1->id], $tenant->id), 'trocas');
    }

    public function test_um_cliente_nao_ve_nem_coleta_o_oleo_de_outro(): void
    {
        [$a, $adminA] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $troca = $this->oleo->registrar($this->veiculo($a), $this->dados());

        $this->actingAs($adminB);
        $this->assertSame(0, FrotaTrocaOleo::count());
        $this->erroEm(fn () => $this->oleo->registrarColeta(['coletada_em' => now()->toDateString(), 'empresa_coletora' => 'X'], [$troca->id], $b->id), 'trocas');

        $this->actingAs($adminA);
        $this->assertSame(1, FrotaTrocaOleo::count());
        $this->assertSame(0, FrotaColetaOleoUsado::count());
    }

    public function test_oleo_com_estoque_baixa_os_litros_e_saldo_insuficiente_recusa_tudo(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        [$peca, $alm] = $this->estoque($tenant, saldo: 25);

        $this->oleo->registrar($v, $this->dados(['litros' => 20, 'peca_id' => $peca->id, 'almoxarifado_id' => $alm->id]));
        $this->assertSame(5.0, $this->saldo($peca, $alm));
        $this->assertSame('exit_fleet', StockMovement::where('part_id', $peca->id)->latest('id')->value('movement_type'));

        $this->erroEm(fn () => $this->oleo->registrar($v, $this->dados(['litros' => 10, 'odometro' => 10500, 'peca_id' => $peca->id, 'almoxarifado_id' => $alm->id])), 'litros');
        $this->assertSame(5.0, $this->saldo($peca, $alm));
        $this->assertSame(1, FrotaTrocaOleo::where('ativo_id', $v->id)->count());
    }

    public function test_oleo_sem_item_escolhido_nao_mexe_no_estoque(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        [$peca, $alm] = $this->estoque($tenant, saldo: 25);

        $this->oleo->registrar($this->veiculo($tenant), $this->dados());

        $this->assertSame(25.0, $this->saldo($peca, $alm));
        $this->assertSame(0, StockMovement::where('part_id', $peca->id)->count());
    }

    public function test_pneu_entra_sai_na_montagem_e_volta_ao_estoque_na_remocao(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        [$peca, $alm] = $this->estoque($tenant, 'Pneu 295/80', 'UN');
        $pneu = FrotaPneu::create(['tenant_id' => $tenant->id, 'numero_fogo' => 'F'.uniqid(), 'marca' => 'X', 'medida' => '295/80', 'peca_id' => $peca->id, 'almoxarifado_id' => $alm->id]);

        app(EstoqueFrotaService::class)->entrada($pneu, 1, 'entry_purchase', 'cadastro');
        $this->assertSame(1.0, $this->saldo($peca, $alm));

        (new PneuService)->montar($pneu, $v, 'E1-LE', 10000, $admin);
        $this->assertSame(0.0, $this->saldo($peca, $alm));

        (new PneuService)->remover($pneu->fresh(), 'outro', 10500, $admin);
        $this->assertSame(1.0, $this->saldo($peca, $alm));
    }

    public function test_pneu_descartado_nao_volta_ao_estoque_e_sem_saldo_a_montagem_e_recusada(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        [$peca, $alm] = $this->estoque($tenant, 'Pneu', 'UN', 1);
        $pneu = FrotaPneu::create(['tenant_id' => $tenant->id, 'numero_fogo' => 'F'.uniqid(), 'peca_id' => $peca->id, 'almoxarifado_id' => $alm->id]);
        $semSaldo = FrotaPneu::create(['tenant_id' => $tenant->id, 'numero_fogo' => 'G'.uniqid(), 'peca_id' => $peca->id, 'almoxarifado_id' => Warehouse::create(['tenant_id' => $tenant->id, 'name' => 'Vazio', 'is_active' => true])->id]);

        (new PneuService)->montar($pneu, $v, 'E1-LE', 10000, $admin);
        (new PneuService)->remover($pneu->fresh(), 'descarte', 10200, $admin);
        $this->assertSame(0.0, $this->saldo($peca, $alm));

        $this->erroEm(fn () => (new PneuService)->montar($semSaldo, $v, 'E1-LD', 10200, $admin), 'pneu');
        $this->assertSame(FrotaPneu::ESTOQUE, $semSaldo->fresh()->situacao);
    }

    public function test_bateria_baixa_na_instalacao_e_volta_so_quando_reaproveitada(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        [$peca, $alm] = $this->estoque($tenant, 'Bateria 100Ah', 'UN', 2);
        $b1 = FrotaBateria::create(['tenant_id' => $tenant->id, 'marca' => 'M', 'peca_id' => $peca->id, 'almoxarifado_id' => $alm->id]);
        $b2 = FrotaBateria::create(['tenant_id' => $tenant->id, 'marca' => 'M', 'peca_id' => $peca->id, 'almoxarifado_id' => $alm->id]);

        (new BateriaService)->instalar($b1, $v, 10000, $admin);
        $this->assertSame(1.0, $this->saldo($peca, $alm));

        (new BateriaService)->remover($b1->fresh(), 'desgaste', 10100, $admin);
        $this->assertSame(1.0, $this->saldo($peca, $alm));

        (new BateriaService)->instalar($b2, $v, 10100, $admin);
        (new BateriaService)->remover($b2->fresh(), 'outro', 10200, $admin);
        $this->assertSame(1.0, $this->saldo($peca, $alm));
    }

    public function test_catalogo_da_frota_cria_categorias_e_itens_sem_duplicar(): void
    {
        [$tenant] = $this->cliente();

        $primeira = (new CatalogoEstoqueFrota)->garantir($tenant->id);
        $segunda = (new CatalogoEstoqueFrota)->garantir($tenant->id);

        $this->assertSame(4, $primeira['categorias']);
        $this->assertGreaterThan(20, $primeira['itens']);
        $this->assertSame(['categorias' => 0, 'itens' => 0], $segunda);
        $this->assertTrue(Part::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Arla 32')->exists());
    }

    public function test_celular_registra_a_troca_e_mostra_erro_de_litros_quebrados(): void
    {
        [$tenant, $admin] = $this->cliente();
        $v = $this->veiculo($tenant);
        $this->actingAs($admin);

        Livewire::test(OleoFrotaMobile::class, ['assetId' => $v->id])
            ->set('litros', '2.5')->set('produto', '15W40')->call('salvar')
            ->assertSet('mensagem', null)
            ->set('litros', '20')->call('salvar')
            ->assertSet('mensagem', 'Troca registrada.');

        $this->assertSame(1, FrotaTrocaOleo::where('ativo_id', $v->id)->count());
    }
}
