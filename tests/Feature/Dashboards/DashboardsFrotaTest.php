<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Blocks\Frota\ConsumoMedio;
use App\Dashboards\Blocks\Frota\GastoComCombustivel;
use App\Dashboards\Blocks\Frota\MultasEmAberto;
use App\Dashboards\Blocks\Frota\SinistrosEmAberto;
use App\Dashboards\Blocks\Frota\TrocasDeOleoAVencer;
use App\Dashboards\DashboardRenderer;
use App\Filament\Pages\VisaoGeralFrota;
use App\Models\Asset;
use App\Models\FrotaAbastecimento;
use App\Models\FrotaMulta;
use App\Models\FrotaSinistro;
use App\Models\FrotaTrocaOleo;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardsFrotaTest extends TestCase
{
    use DatabaseTransactions;

    private const FEATURES = ['tabela_frota_abastecimentos' => true, 'tabela_frota_multas' => true, 'tabela_frota_sinistros' => true, 'tabela_frota_trocas_oleo' => true];

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(array $features = self::FEATURES): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'fr-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function criar(string $modelo, array $attrs): Model
    {
        return $modelo::unguarded(fn () => $modelo::create($attrs));
    }

    private function veiculo(Tenant $t, string $nome = 'Caminhão'): Asset
    {
        return $this->criar(Asset::class, ['tenant_id' => $t->id, 'name' => $nome, 'status' => 'disponivel', 'is_vehicle' => true, 'placa' => 'ABC1D23']);
    }

    private function abastecer(Tenant $t, Asset $v, float $total, array $extra = []): FrotaAbastecimento
    {
        return $this->criar(FrotaAbastecimento::class, $extra + [
            'tenant_id' => $t->id, 'ativo_id' => $v->id, 'abastecido_em' => now(), 'odometro' => 1000, 'combustivel' => 'diesel_s10',
            'litros' => 50, 'valor_litro' => $total / 50, 'valor_total' => $total,
        ]);
    }

    public function test_sem_modulos_de_frota_a_pagina_nao_existe(): void
    {
        [, $admin] = $this->empresa(['tabela_assets' => true]);
        $this->actingAs($admin);

        $this->assertFalse(VisaoGeralFrota::canAccess());
    }

    public function test_so_blocos_dos_modulos_contratados(): void
    {
        [, $admin] = $this->empresa(['tabela_frota_abastecimentos' => true]);
        $this->actingAs($admin);

        $chaves = array_column(DashboardRenderer::blocks('frota', $admin), 'key');

        $this->assertContains('frota.combustivel_mes', $chaves);
        $this->assertNotContains('frota.multas_em_aberto', $chaves);
        $this->assertNotContains('frota.trocas_oleo_a_vencer', $chaves);
    }

    public function test_combustivel_no_mes_so_soma_o_proprio_tenant_e_compara_com_o_anterior(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $va = $this->veiculo($a);
        $this->abastecer($a, $va, 300);
        $this->abastecer($a, $va, 400, ['abastecido_em' => now()->subMonth()]);
        $this->abastecer($b, $this->veiculo($b), 9999);
        $this->actingAs($adminA);

        $p = (new GastoComCombustivel)->data([]);

        $this->assertStringContainsString('300,00', $p['value']);
        $this->assertStringContainsString('-25%', $p['delta']);
        $this->assertSame('up', $p['tone']); // gastou menos
    }

    public function test_consumo_medio_ignora_abastecimento_sem_consumo(): void
    {
        [$t, $admin] = $this->empresa();
        $v = $this->veiculo($t);
        $this->abastecer($t, $v, 100, ['consumo_km_l' => 4]);
        $this->abastecer($t, $v, 100, ['consumo_km_l' => 6]);
        $this->abastecer($t, $v, 100);
        $this->actingAs($admin);

        $this->assertSame('5,0', (new ConsumoMedio)->data([])['value']);
    }

    public function test_multas_em_aberto_exclui_pagas_e_avisa_as_que_vencem(): void
    {
        [$t, $admin] = $this->empresa();
        $v = $this->veiculo($t);
        $base = ['tenant_id' => $t->id, 'ativo_id' => $v->id, 'infracao_em' => now()->subDays(20), 'descricao' => 'Excesso', 'gravidade' => 'leve'];
        $this->criar(FrotaMulta::class, $base + ['numero_auto' => 'A1', 'valor' => 130, 'vencimento' => now()->addDays(3)->toDateString(), 'situacao' => 'aberta']);
        $this->criar(FrotaMulta::class, $base + ['numero_auto' => 'A2', 'valor' => 200, 'vencimento' => now()->addDays(40)->toDateString(), 'situacao' => 'aberta']);
        $this->criar(FrotaMulta::class, $base + ['numero_auto' => 'A3', 'valor' => 500, 'vencimento' => now()->addDays(3)->toDateString(), 'situacao' => 'paga']);
        $this->actingAs($admin);

        $p = (new MultasEmAberto)->data([]);

        $this->assertSame(2, $p['value']);
        $this->assertStringContainsString('330,00', $p['delta']);
        $this->assertStringContainsString('1 vencem', $p['delta']);
    }

    public function test_sinistros_em_aberto_conta_os_com_veiculo_parado(): void
    {
        [$t, $admin] = $this->empresa();
        $v = $this->veiculo($t);
        $base = ['tenant_id' => $t->id, 'ativo_id' => $v->id, 'tipo' => 'colisao', 'ocorrido_em' => now()->subDays(3), 'descricao' => 'x'];
        $this->criar(FrotaSinistro::class, $base + ['situacao' => 'aberto', 'veiculo_parado' => true]);
        $this->criar(FrotaSinistro::class, $base + ['situacao' => 'em_reparo', 'veiculo_parado' => false]);
        $this->criar(FrotaSinistro::class, $base + ['situacao' => 'encerrado', 'veiculo_parado' => true]);
        $this->actingAs($admin);

        $p = (new SinistrosEmAberto)->data([]);

        $this->assertSame(2, $p['value']);
        $this->assertStringContainsString('1 com veículo parado', $p['delta']);
    }

    public function test_troca_de_oleo_usa_so_a_ultima_de_cada_veiculo(): void
    {
        [$t, $admin] = $this->empresa();
        $v = $this->veiculo($t, 'Munck');
        $base = ['tenant_id' => $t->id, 'ativo_id' => $v->id, 'tipo' => 'motor', 'odometro' => 1, 'litros' => 20, 'produto' => '15W40'];
        // a antiga vence em breve, mas a mais recente já empurrou a próxima para longe: não deve listar
        $this->criar(FrotaTrocaOleo::class, $base + ['realizado_em' => now()->subMonths(6), 'proxima_troca_data' => now()->addDays(5)->toDateString()]);
        $this->criar(FrotaTrocaOleo::class, $base + ['realizado_em' => now()->subDay(), 'proxima_troca_data' => now()->addMonths(6)->toDateString()]);
        $vencida = $this->veiculo($t, 'Toco');
        $this->criar(FrotaTrocaOleo::class, ['ativo_id' => $vencida->id] + $base + ['realizado_em' => now()->subMonths(8), 'proxima_troca_data' => now()->subDays(4)->toDateString()]);
        $this->actingAs($admin);

        $linhas = (new TrocasDeOleoAVencer)->data([])['rows'];

        $this->assertCount(1, $linhas);
        $this->assertSame('Toco', $linhas[0][0]);
        $this->assertSame('crit', $linhas[0][3]['pill']);
    }

    public function test_a_pagina_renderiza(): void
    {
        [$t, $admin] = $this->empresa();
        $this->abastecer($t, $this->veiculo($t, 'Caminhão Alfa'), 250);
        $this->actingAs($admin);

        Livewire::test(VisaoGeralFrota::class)
            ->assertSee('Combustível no mês')
            ->assertSee('Trocas de óleo a vencer')
            ->assertSee('Caminhão Alfa');
    }
}
